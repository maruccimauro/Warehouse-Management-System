<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\ConcurrentRun;

/*
| StockLedger bajo concurrencia real (E3.14): procesos en paralelo con su
| propia conexión, que arrancan juntos por la barrera de ConcurrentRun. Grupo
| concurrency, fuera de la suite por defecto: composer test:concurrency.
*/

function concurrentLocation(Warehouse $warehouse, string $code, ?int $maxUnits = null): Location
{
    $zone = $warehouse->zones()->firstOrCreate(['code' => 'ALM'], ['name' => 'Almacenamiento', 'type' => ZoneType::Storage]);

    return $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => $code, 'max_units' => $maxUnits]);
}

/**
 * Carga inicial confirmada, visible para los procesos hijos.
 */
function concurrentLoad(int $userId, Product $product, Location $location, int $quantity): InventoryOperation
{
    return DB::transaction(fn (): InventoryOperation => app(StockLedger::class)->apply(new OperationDraft(
        OperationType::AdjustmentIn,
        $location->warehouse_id,
        $userId,
        [new MovementLine($product->id, $location->id, 561, $quantity)],
        reasonCode: AdjustmentReason::InitialLoad,
    )));
}

function concurrentOnHand(Product $product, Location $location): int
{
    return (int) Stock::query()->where('product_id', $product->id)->where('location_id', $location->id)->value('quantity_on_hand');
}

/**
 * Cuántos procesos terminaron con cada resultado: ok, o el código del rechazo.
 *
 * @param  list<array<string, mixed>>  $results
 * @return array<string, int>
 */
function concurrentOutcomes(array $results): array
{
    $outcomes = [];

    foreach ($results as $result) {
        $key = $result['status'] === 'rejected' ? (string) $result['code'] : (string) $result['status'];
        $outcomes[$key] = ($outcomes[$key] ?? 0) + 1;

        if ($result['status'] === 'error') {
            throw new RuntimeException('Un proceso falló: '.json_encode($result));
        }
    }

    ksort($outcomes);

    return $outcomes;
}

/**
 * El libro cuadra: el físico de cada fila es la suma de sus movimientos (INV-002).
 */
function concurrentLedgerBalances(): bool
{
    return Stock::query()->get()->every(
        fn (Stock $stock): bool => $stock->quantity_on_hand === (int) StockMovement::query()->where('stock_id', $stock->id)->sum('delta'),
    );
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->a = concurrentLocation($this->warehouse, 'A-01-01');
    $this->b = concurrentLocation($this->warehouse, 'B-01-01');
    $this->product = Product::factory()->create();
    $this->base = ['user' => $this->user->id, 'warehouse' => $this->warehouse->id, 'product' => $this->product->id];
});

it('lets exactly as many withdrawals as the stock covers (INV-001)', function () {
    concurrentLoad($this->user->id, $this->product, $this->a, 100);

    $results = ConcurrentRun::scenario('withdraw', array_fill(0, 50, [...$this->base, 'location' => $this->a->id, 'quantity' => 10]));

    expect(concurrentOutcomes($results))->toBe(['INSUFFICIENT_STOCK' => 40, 'ok' => 10])
        ->and(concurrentOnHand($this->product, $this->a))->toBe(0)
        ->and(InventoryOperation::query()->where('type', OperationType::AdjustmentOut)->count())->toBe(10)
        ->and(concurrentLedgerBalances())->toBeTrue();
});

it('runs crossed transfers without unresolved deadlocks and keeps the totals (INV-004)', function () {
    concurrentLoad($this->user->id, $this->product, $this->a, 100);
    concurrentLoad($this->user->id, $this->product, $this->b, 100);

    $payloads = [];

    for ($i = 0; $i < 20; $i++) {
        [$from, $to] = $i % 2 === 0 ? [$this->a, $this->b] : [$this->b, $this->a];
        $payloads[] = [...$this->base, 'from' => $from->id, 'to' => $to->id, 'quantity' => 5];
    }

    $results = ConcurrentRun::scenario('transfer', $payloads);

    expect(concurrentOutcomes($results))->toBe(['ok' => 20])
        ->and(concurrentOnHand($this->product, $this->a))->toBe(100)
        ->and(concurrentOnHand($this->product, $this->b))->toBe(100)
        ->and(concurrentLedgerBalances())->toBeTrue();
});

it('does not let simultaneous entries exceed max_units together (REG-010)', function () {
    $small = concurrentLocation($this->warehouse, 'C-01-01', maxUnits: 10);

    $results = ConcurrentRun::scenario('enter', array_fill(0, 10, [...$this->base, 'location' => $small->id, 'quantity' => 3]));

    expect(concurrentOutcomes($results))->toBe(['LOCATION_CAPACITY_EXCEEDED' => 7, 'ok' => 3])
        ->and(concurrentOnHand($this->product, $small))->toBe(9);
});

it('lets simultaneous reversals of one line add up to its quantity at most (INV-007, REG-012)', function () {
    concurrentLoad($this->user->id, $this->product, $this->a, 10);
    // Stock de sobra en el destino: el límite lo pone el saldo revertible, no el disponible.
    concurrentLoad($this->user->id, $this->product, $this->b, 100);
    $moved = DB::transaction(fn (): InventoryOperation => app(StockLedger::class)->apply(new OperationDraft(
        OperationType::Transfer,
        $this->warehouse->id,
        $this->user->id,
        [
            new MovementLine($this->product->id, $this->a->id, 931, -10),
            new MovementLine($this->product->id, $this->b->id, 931, 10),
        ],
    )));

    $results = ConcurrentRun::scenario('reverse', array_fill(0, 5, [...$this->base, 'operation' => $moved->id, 'quantity' => 4]));

    expect(concurrentOutcomes($results))->toBe(['REVERSAL_EXCEEDS_REMAINING' => 3, 'ok' => 2])
        ->and(concurrentOnHand($this->product, $this->a))->toBe(8)
        ->and(concurrentOnHand($this->product, $this->b))->toBe(102)
        ->and(concurrentLedgerBalances())->toBeTrue();
});
