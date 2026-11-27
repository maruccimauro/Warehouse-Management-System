<?php

use App\Modules\Catalog\Actions\SetProductAdjustmentThresholds;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
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
| Carga inicial de stock (E3.06): POST /inventory/initial-loads con varias
| posiciones, contabilizadas en una operación adjustment_in con motivo
| initial_load y un movimiento 561 por posición. No pasa por el umbral de
| aprobación: el control es el permiso inventory.initial_load (Administrador y
| Gerente de operaciones). Las ramas de error de StockLedger tienen su prueba
| en StockLedgerTest; acá se verifica que lleguen por la API y que la carga
| sea atómica (INV-004).
*/

function initialLoadLocation(Warehouse $warehouse, string $code): Location
{
    $zone = $warehouse->zones()->firstOrCreate(['code' => 'ALM'], ['name' => 'Almacenamiento', 'type' => ZoneType::Storage]);

    return $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => $code]);
}

function initialLoadOnHand(Product $product, Location $location): int
{
    return (int) Stock::query()->where('product_id', $product->id)->where('location_id', $location->id)->value('quantity_on_hand');
}

/**
 * @param  list<array{0: Product, 1: Location, 2: int}>  $items
 * @param  array<string, mixed>  $extra
 */
function initialLoadPost(array $items, array $extra = [], ?string $key = null): TestResponse
{
    $test = test();

    return $test->postJson('/api/v1/inventory/initial-loads', [
        'warehouse_id' => $test->warehouse->id,
        'items' => array_map(fn (array $item): array => [
            'product_id' => $item[0]->id,
            'location_id' => $item[1]->id,
            'quantity' => $item[2],
        ], $items),
        ...$extra,
    ], [Idempotency::HEADER => $key ?? (string) Str::uuid()]);
}

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->warehouse = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->a = initialLoadLocation($this->warehouse, 'A-01-01');
    $this->b = initialLoadLocation($this->warehouse, 'B-01-01');
    $this->bolt = Product::factory()->create(['sku' => 'TOR-M8']);
    $this->nut = Product::factory()->create(['sku' => 'TUE-M8']);
    $this->user = userWithAccess(Role::Administrator);
    $this->actingAs($this->user);
});

describe('posting', function () {
    it('posts every item in one adjustment_in operation with movement 561 (201)', function () {
        $response = initialLoadPost([[$this->bolt, $this->a, 500], [$this->nut, $this->b, 200]], ['notes' => '  Inventario de arranque  ']);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'adjustment_in')
            ->assertJsonPath('data.reason_code', 'initial_load')
            ->assertJsonPath('data.reference_type', null)
            ->assertJsonPath('data.notes', 'Inventario de arranque')
            ->assertJsonPath('data.performed_by', $this->user->id);

        expect(collect($response->json('data.movements'))->map(fn (array $line): array => [
            $line['item_number'], $line['sku'], $line['location_code'], $line['movement_code'], $line['delta'], $line['balance_after'],
        ])->all())->toBe([
            [1, 'TOR-M8', 'A-01-01', 561, 500, 500],
            [2, 'TUE-M8', 'B-01-01', 561, 200, 200],
        ])
            ->and(initialLoadOnHand($this->bolt, $this->a))->toBe(500)
            ->and(initialLoadOnHand($this->nut, $this->b))->toBe(200)
            ->and(DB::table('audit_logs')->where('action', 'inventory_operation.created')->where('operation_id', $response->json('data.id'))->exists())->toBeTrue();
    });

    it('adds to the stock that already exists, as it is admitted at any time', function () {
        initialLoadPost([[$this->bolt, $this->a, 10]])->assertCreated();

        initialLoadPost([[$this->bolt, $this->a, 5]])
            ->assertCreated()
            ->assertJsonPath('data.movements.0.balance_after', 15);
    });

    it('ignores the approval threshold of the item', function () {
        app(SetProductAdjustmentThresholds::class)($this->bolt, $this->warehouse, 1, 1);

        initialLoadPost([[$this->bolt, $this->a, 1000]])->assertCreated();

        expect(initialLoadOnHand($this->bolt, $this->a))->toBe(1000)
            ->and(DB::table('stock_adjustments')->count())->toBe(0);
    });

    it('accepts a discontinued or blocked item, as an adjustment does (REG-017)', function (ProductStatus $status) {
        // Un discontinuado deja agotar el stock que existe; un bloqueado admite
        // los ajustes que corrigen.
        $this->bolt->forceFill(['status' => $status])->save();

        initialLoadPost([[$this->bolt, $this->a, 3]])->assertCreated();
    })->with([ProductStatus::Discontinued, ProductStatus::Blocked]);

    it('replays the stored response with the same key and posts once (ADR-007)', function () {
        $key = (string) Str::uuid();

        $first = initialLoadPost([[$this->bolt, $this->a, 7]], key: $key)->assertCreated();
        $second = initialLoadPost([[$this->bolt, $this->a, 7]], key: $key)->assertCreated();

        expect($second->headers->get('Idempotent-Replayed'))->toBe('true')
            ->and($second->json('data.id'))->toBe($first->json('data.id'))
            ->and(initialLoadOnHand($this->bolt, $this->a))->toBe(7);
    });

    it('is reversible from the ledger with movement 562', function () {
        $loaded = InventoryOperation::query()->findOrFail(initialLoadPost([[$this->bolt, $this->a, 8]])->json('data.id'));
        /** @var StockMovement $line */
        $line = $loaded->movements()->sole();

        DB::transaction(fn () => app(StockLedger::class)->apply(new OperationDraft(
            OperationType::Reversal,
            $this->warehouse->id,
            $this->user->id,
            [new MovementLine($this->bolt->id, $this->a->id, 562, -3, reversesMovementId: $line->id)],
            reversesOperationId: $loaded->id,
            notes: 'Cantidad cargada de más',
        )));

        expect(initialLoadOnHand($this->bolt, $this->a))->toBe(5);
    });
});

describe('business rules', function () {
    it('rejects a system location and posts nothing (REG-014)', function (SystemLocationRole $role) {
        $system = $this->warehouse->locations()->where('system_role', $role)->firstOrFail();

        initialLoadPost([[$this->bolt, $this->a, 1], [$this->bolt, $system, 5]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'SYSTEM_LOCATION_NOT_ALLOWED')
            ->assertJsonPath('context.item', 2)
            ->assertJsonPath('context.system_role', $role->value);

        expect(InventoryOperation::query()->count())->toBe(0);
    })->with(SystemLocationRole::cases());

    it('rejects a blocked location and posts no item (INV-004, INV-009)', function () {
        $this->b->forceFill(['status' => LocationStatus::Blocked, 'status_reason' => 'Rack dañado'])->save();

        initialLoadPost([[$this->bolt, $this->a, 1], [$this->nut, $this->b, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'LOCATION_BLOCKED');

        expect(InventoryOperation::query()->count())->toBe(0)
            ->and(initialLoadOnHand($this->bolt, $this->a))->toBe(0);
    });

    it('rejects an inactive warehouse', function () {
        $this->warehouse->forceFill(['is_active' => false])->save();

        initialLoadPost([[$this->bolt, $this->a, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'WAREHOUSE_INACTIVE');
    });

    it('rejects more than the capacity of the location (REG-010)', function () {
        $this->a->forceFill(['max_units' => 10])->save();

        initialLoadPost([[$this->bolt, $this->a, 11]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'LOCATION_CAPACITY_EXCEEDED');
    });
});

describe('validation', function () {
    it('rejects a location of another warehouse on its field', function () {
        $other = initialLoadLocation(Warehouse::factory()->create(), 'A-01-01');

        initialLoadPost([[$this->bolt, $other, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.location_id'], 'errors');
    });

    it('rejects two items on the same stock row (INV-004)', function () {
        initialLoadPost([[$this->bolt, $this->a, 1], [$this->nut, $this->a, 1], [$this->bolt, $this->a, 2]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.2'], 'errors');
    });

    it('rejects a malformed request', function (Closure $body, string $field) {
        $this->postJson('/api/v1/inventory/initial-loads', $body->call($this), [Idempotency::HEADER => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'no items' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => []], 'items'],
        'too many items' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => array_fill(0, 101, ['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1])], 'items'],
        'zero quantity' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 0]]], 'items.0.quantity'],
        'negative quantity' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => -3]]], 'items.0.quantity'],
        'unknown item field' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1, 'reason_code' => 'found']]], 'items.0'],
        'unknown product' => [fn () => ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => 999999, 'location_id' => $this->a->id, 'quantity' => 1]]], 'items.0.product_id'],
        'notes too long' => [fn () => ['warehouse_id' => $this->warehouse->id, 'notes' => str_repeat('x', 501), 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1]]], 'notes'],
        'no warehouse' => [fn () => ['items' => []], 'warehouse_id'],
    ]);
});

describe('authorization (REG-005)', function () {
    it('lets the operations lead load in an assigned warehouse', function () {
        $this->actingAs(userWithAccess(Role::OperationsLead, [$this->warehouse]));

        initialLoadPost([[$this->bolt, $this->a, 4]])->assertCreated();
    });

    it('lets the operations manager load in any warehouse without assignment', function () {
        $this->actingAs(userWithAccess(Role::OperationsManager));

        initialLoadPost([[$this->bolt, $this->a, 4]])->assertCreated();
    });

    it('forbids the supervisor, whose adjustments go through the threshold', function () {
        $this->actingAs(userWithAccess(Role::Supervisor, [$this->warehouse]));

        initialLoadPost([[$this->bolt, $this->a, 4]])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    });

    it('forbids a warehouse the lead does not operate in, without revealing whether it exists', function (Closure $warehouseId) {
        $this->actingAs(userWithAccess(Role::OperationsLead, [$this->warehouse]));

        $this->postJson('/api/v1/inventory/initial-loads', ['warehouse_id' => $warehouseId->call($this), 'items' => []], [Idempotency::HEADER => (string) Str::uuid()])
            ->assertForbidden()
            ->assertJsonPath('code', 'WAREHOUSE_NOT_ASSIGNED');
    })->with([
        'visible, not assigned' => [fn () => app(CreateWarehouse::class)('NORTE', 'Depósito norte')->id],
        'nonexistent' => [fn () => 999999],
    ]);
});
