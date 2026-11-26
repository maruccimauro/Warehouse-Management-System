<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Enums\SystemLocationRole;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| Transferencia de stock (E3.04, CU-03): POST /inventory/transfers con varias
| posiciones, cada una con dos líneas 931 y el mismo item_number. Las ramas de
| error de StockLedger tienen su prueba en StockLedgerTest; acá se verifica
| que lleguen por la API y que la operación sea atómica (INV-004).
*/

function transferLocation(Warehouse $warehouse, string $code, ?int $maxUnits = null): Location
{
    $zone = $warehouse->zones()->firstOrCreate(['code' => 'ALM'], ['name' => 'Almacenamiento', 'type' => ZoneType::Storage]);

    return $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => $code, 'max_units' => $maxUnits]);
}

function transferLoad(Product $product, Location $location, int $quantity): void
{
    DB::transaction(fn () => app(StockLedger::class)->apply(new OperationDraft(
        OperationType::AdjustmentIn,
        $location->warehouse_id,
        test()->user->id,
        [new MovementLine($product->id, $location->id, 561, $quantity)],
        reasonCode: AdjustmentReason::InitialLoad,
    )));
}

function transferOnHand(Product $product, Location $location): int
{
    return (int) Stock::query()->where('product_id', $product->id)->where('location_id', $location->id)->value('quantity_on_hand');
}

/**
 * @param  list<array{0: Product, 1: Location, 2: Location, 3: int}>  $items
 * @param  array<string, mixed>  $extra
 */
function transferPost(array $items, array $extra = [], ?string $key = null): TestResponse
{
    $test = test();

    return $test->postJson('/api/v1/inventory/transfers', [
        'warehouse_id' => $test->warehouse->id,
        'items' => array_map(fn (array $item): array => [
            'product_id' => $item[0]->id,
            'from_location_id' => $item[1]->id,
            'to_location_id' => $item[2]->id,
            'quantity' => $item[3],
        ], $items),
        ...$extra,
    ], [Idempotency::HEADER => $key ?? (string) Str::uuid()]);
}

function transfers(): int
{
    return InventoryOperation::query()->where('type', OperationType::Transfer)->count();
}

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->warehouse = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->a = transferLocation($this->warehouse, 'A-01-01');
    $this->b = transferLocation($this->warehouse, 'B-01-01');
    $this->c = transferLocation($this->warehouse, 'C-01-01');
    $this->bolt = Product::factory()->create(['sku' => 'TOR-M8']);
    $this->nut = Product::factory()->create(['sku' => 'TUE-M8']);
    $this->user = userWithAccess(Role::Operator, [$this->warehouse]);

    transferLoad($this->bolt, $this->a, 50);
    transferLoad($this->nut, $this->a, 20);
    $this->actingAs($this->user);
});

describe('posting', function () {
    it('posts several items as one operation with two 931 lines per item', function () {
        $response = transferPost([[$this->bolt, $this->a, $this->b, 30], [$this->nut, $this->a, $this->c, 5]], ['notes' => '  Reposición del pasillo B  ']);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.warehouse_id', $this->warehouse->id)
            ->assertJsonPath('data.notes', 'Reposición del pasillo B')
            ->assertJsonPath('data.performed_by', $this->user->id);

        expect($response->json('data.number'))->toMatch('/^49\d{8}$/')
            ->and(collect($response->json('data.movements'))->map(fn (array $line): array => [
                $line['item_number'], $line['sku'], $line['location_code'], $line['movement_code'], $line['delta'], $line['balance_after'],
            ])->all())->toBe([
                [1, 'TOR-M8', 'A-01-01', 931, -30, 20],
                [1, 'TOR-M8', 'B-01-01', 931, 30, 30],
                [2, 'TUE-M8', 'A-01-01', 931, -5, 15],
                [2, 'TUE-M8', 'C-01-01', 931, 5, 5],
            ])
            ->and(transferOnHand($this->bolt, $this->a))->toBe(20)
            ->and(transferOnHand($this->bolt, $this->b))->toBe(30)
            ->and(transferOnHand($this->nut, $this->c))->toBe(5)
            ->and(DB::table('audit_logs')->where('action', 'inventory_operation.created')->where('operation_id', $response->json('data.id'))->exists())->toBeTrue();
    });

    it('moves the same item from two sources in one transfer, paired by item number', function () {
        transferLoad($this->bolt, $this->c, 10);
        $d = transferLocation($this->warehouse, 'D-01-01');

        $response = transferPost([[$this->bolt, $this->a, $this->b, 5], [$this->bolt, $this->c, $d, 4]])->assertCreated();

        expect(collect($response->json('data.movements'))->map(fn (array $line): array => [$line['item_number'], $line['location_code'], $line['delta']])->all())
            ->toBe([[1, 'A-01-01', -5], [1, 'B-01-01', 5], [2, 'C-01-01', -4], [2, 'D-01-01', 4]])
            ->and(transferOnHand($this->bolt, $this->b))->toBe(5)
            ->and(transferOnHand($this->bolt, $d))->toBe(4);
    });

    it('transfers what is free in a system location such as RECEPCION (DM-14)', function () {
        $receiving = $this->warehouse->locations()->where('system_role', SystemLocationRole::Receiving)->firstOrFail();
        transferLoad($this->bolt, $receiving, 8);

        transferPost([[$this->bolt, $receiving, $this->b, 8]])->assertCreated();

        expect(transferOnHand($this->bolt, $receiving))->toBe(0);
    });

    it('runs once and replays the stored response for the same key (ADR-007)', function () {
        $first = transferPost([[$this->bolt, $this->a, $this->b, 10]], key: 'traspaso-1');
        $second = transferPost([[$this->bolt, $this->a, $this->b, 10]], key: 'traspaso-1');

        $first->assertCreated();
        $second->assertCreated()->assertHeader(Idempotency::REPLAYED_HEADER, 'true');

        // El cuerpo guardado en JSONB vuelve con las claves en otro orden: mismo contenido.
        expect($second->json())->toEqual($first->json())
            ->and(transfers())->toBe(1)
            ->and(transferOnHand($this->bolt, $this->a))->toBe(40)
            ->and(InventoryOperation::query()->where('type', OperationType::Transfer)->value('idempotency_key'))->toBe('traspaso-1');
    });

    it('requires an Idempotency-Key (428)', function () {
        $this->postJson('/api/v1/inventory/transfers', ['warehouse_id' => $this->warehouse->id])
            ->assertStatus(428)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
    });
});

describe('business rules', function () {
    it('rejects an item whose source is its destination and posts none of the items (INV-004)', function () {
        transferPost([[$this->bolt, $this->a, $this->b, 5], [$this->nut, $this->c, $this->c, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'SAME_SOURCE_AND_DESTINATION')
            ->assertJsonPath('context.item', 2)
            ->assertJsonPath('context.location_id', $this->c->id);

        expect(transfers())->toBe(0)->and(transferOnHand($this->bolt, $this->a))->toBe(50);
    });

    it('rejects a system location as destination (REG-014)', function (SystemLocationRole $role) {
        $system = $this->warehouse->locations()->where('system_role', $role)->firstOrFail();

        transferPost([[$this->bolt, $this->a, $system, 5]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'SYSTEM_LOCATION_NOT_ALLOWED')
            ->assertJsonPath('context.item', 1)
            ->assertJsonPath('context.system_role', $role->value);

        expect(transfers())->toBe(0);
    })->with(SystemLocationRole::cases());

    it('rolls back every item when one has not enough free stock (INV-001, INV-004)', function () {
        transferPost([[$this->bolt, $this->a, $this->b, 30], [$this->nut, $this->a, $this->c, 21]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INSUFFICIENT_STOCK');

        expect(transfers())->toBe(0)
            ->and(transferOnHand($this->bolt, $this->a))->toBe(50)
            ->and(transferOnHand($this->bolt, $this->b))->toBe(0);
    });

    it('rejects a destination over its capacity (REG-010)', function () {
        $small = transferLocation($this->warehouse, 'D-01-01', maxUnits: 10);

        transferPost([[$this->bolt, $this->a, $small, 11]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'LOCATION_CAPACITY_EXCEEDED');
    });

    it('rejects a blocked source or destination (INV-009)', function () {
        $this->b->forceFill(['status' => LocationStatus::Blocked, 'status_reason' => 'Rack dañado'])->save();

        transferPost([[$this->bolt, $this->a, $this->b, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'LOCATION_BLOCKED');
    });
});

describe('validation', function () {
    it('rejects a location of another warehouse on its field', function () {
        $other = transferLocation(Warehouse::factory()->create(), 'A-01-01');

        transferPost([[$this->bolt, $this->a, $other, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.to_location_id'], 'errors');
    });

    it('rejects two items that move the same stock row', function () {
        transferPost([[$this->bolt, $this->a, $this->b, 1], [$this->bolt, $this->b, $this->c, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1'], 'errors');
    });

    it('rejects a malformed request', function (Closure $body, string $field) {
        $this->postJson('/api/v1/inventory/transfers', $body->call($this), [Idempotency::HEADER => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'no items' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => []], 'items'],
        'too many items' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => array_fill(0, 101, ['product_id' => $this->bolt->id, 'from_location_id' => $this->a->id, 'to_location_id' => $this->b->id, 'quantity' => 1])], 'items'],
        'zero quantity' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => $this->bolt->id, 'from_location_id' => $this->a->id, 'to_location_id' => $this->b->id, 'quantity' => 0]]], 'items.0.quantity'],
        'unknown item field' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => $this->bolt->id, 'from_location_id' => $this->a->id, 'to_location_id' => $this->b->id, 'quantity' => 1, 'lot' => 'X']]], 'items.0'],
        'unknown product' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => 999999, 'from_location_id' => $this->a->id, 'to_location_id' => $this->b->id, 'quantity' => 1]]], 'items.0.product_id'],
        'notes too long' => [fn () => ['warehouse_id' => $this->warehouse->id, 'notes' => str_repeat('x', 501), 'items' => [['product_id' => $this->bolt->id, 'from_location_id' => $this->a->id, 'to_location_id' => $this->b->id, 'quantity' => 1]]], 'notes'],
        'no warehouse' => [fn () => ['items' => []], 'warehouse_id'],
    ]);
});

describe('authorization (REG-005)', function () {
    it('forbids a warehouse the user does not operate in, without revealing whether it exists', function (Closure $warehouseId) {
        $this->postJson('/api/v1/inventory/transfers', ['warehouse_id' => $warehouseId->call($this), 'items' => []], [Idempotency::HEADER => (string) Str::uuid()])
            ->assertForbidden()
            ->assertJsonPath('code', 'WAREHOUSE_NOT_ASSIGNED');
    })->with([
        'visible, not assigned' => [fn () => app(CreateWarehouse::class)('NORTE', 'Depósito norte')->id],
        'nonexistent' => [fn () => 999999],
    ]);

    it('forbids a role without inventory.transfer', function () {
        $this->actingAs(userWithAccess(Role::Auditor, [$this->warehouse]));

        transferPost([[$this->bolt, $this->a, $this->b, 1]])->assertForbidden();

        expect(transfers())->toBe(0);
    });
});
