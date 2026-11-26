<?php

use App\Modules\Catalog\Actions\SetProductAdjustmentThresholds;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockAdjustment;
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
| Ajuste de stock (E3.05, CU-04): POST /inventory/adjustments con una
| dirección, un motivo y varias posiciones. Las que no superan el umbral del
| artículo se contabilizan en una operación que referencia al documento; las
| que lo superan quedan pending sin mover stock (202). Las ramas de error de
| StockLedger tienen su prueba en StockLedgerTest; acá se verifica que lleguen
| por la API y que el documento sea atómico (INV-004).
*/

function adjustmentLocation(Warehouse $warehouse, string $code): Location
{
    $zone = $warehouse->zones()->firstOrCreate(['code' => 'ALM'], ['name' => 'Almacenamiento', 'type' => ZoneType::Storage]);

    return $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => $code]);
}

function adjustmentLoad(Product $product, Location $location, int $quantity): void
{
    DB::transaction(fn () => app(StockLedger::class)->apply(new OperationDraft(
        OperationType::AdjustmentIn,
        $location->warehouse_id,
        test()->user->id,
        [new MovementLine($product->id, $location->id, 561, $quantity)],
        reasonCode: AdjustmentReason::InitialLoad,
    )));
}

function adjustmentOnHand(Product $product, Location $location): int
{
    return (int) Stock::query()->where('product_id', $product->id)->where('location_id', $location->id)->value('quantity_on_hand');
}

function adjustmentThresholds(Product $product, ?int $in, ?int $out): void
{
    app(SetProductAdjustmentThresholds::class)($product, test()->warehouse, $in, $out);
}

/**
 * @param  list<array{0: Product, 1: Location, 2: int}>  $items
 * @param  array<string, mixed>  $extra
 */
function adjustmentPost(string $direction, string $reason, array $items, array $extra = [], ?string $key = null): TestResponse
{
    $test = test();

    return $test->postJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $test->warehouse->id,
        'direction' => $direction,
        'reason_code' => $reason,
        'items' => array_map(fn (array $item): array => [
            'product_id' => $item[0]->id,
            'location_id' => $item[1]->id,
            'quantity' => $item[2],
        ], $items),
        ...$extra,
    ], [Idempotency::HEADER => $key ?? (string) Str::uuid()]);
}

function adjustmentOperations(): int
{
    return InventoryOperation::query()->whereIn('type', [OperationType::AdjustmentIn, OperationType::AdjustmentOut])->whereNotNull('reference_type')->count();
}

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->warehouse = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->a = adjustmentLocation($this->warehouse, 'A-01-01');
    $this->b = adjustmentLocation($this->warehouse, 'B-01-01');
    $this->bolt = Product::factory()->create(['sku' => 'TOR-M8']);
    $this->nut = Product::factory()->create(['sku' => 'TUE-M8']);
    $this->motor = Product::factory()->create(['sku' => 'MOT-1HP']);
    $this->user = userWithAccess(Role::Supervisor, [$this->warehouse]);

    adjustmentLoad($this->bolt, $this->a, 500);
    adjustmentLoad($this->nut, $this->a, 200);
    adjustmentLoad($this->motor, $this->a, 10);
    $this->actingAs($this->user);
});

describe('posting without thresholds', function () {
    it('posts every item in one operation that references the document (201)', function () {
        $response = adjustmentPost('out', 'damage', [[$this->bolt, $this->a, 30], [$this->nut, $this->a, 5]], ['notes' => '  Caja aplastada  ']);

        $response->assertCreated()
            ->assertJsonPath('data.direction', 'out')
            ->assertJsonPath('data.reason_code', 'damage')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.notes', 'Caja aplastada')
            ->assertJsonPath('data.created_by', $this->user->id)
            ->assertJsonPath('data.items.*.status', ['posted', 'posted'])
            ->assertJsonPath('data.items.*.approval_threshold', [null, null])
            ->assertJsonPath('data.operations.0.type', 'adjustment_out')
            ->assertJsonPath('data.operations.0.reason_code', 'damage')
            ->assertJsonPath('data.operations.0.reference_type', 'stock_adjustment')
            ->assertJsonPath('data.operations.0.reference_id', $response->json('data.id'));

        expect($response->json('data.number'))->toMatch('/^51\d{8}$/')
            ->and($response->json('data.items.0.operation_number'))->toBe($response->json('data.operations.0.number'))
            ->and(collect($response->json('data.operations.0.movements'))->map(fn (array $line): array => [
                $line['item_number'], $line['sku'], $line['movement_code'], $line['delta'], $line['balance_after'],
            ])->all())->toBe([
                [1, 'TOR-M8', 551, -30, 470],
                [2, 'TUE-M8', 551, -5, 195],
            ])
            ->and(adjustmentOnHand($this->bolt, $this->a))->toBe(470)
            ->and(DB::table('audit_logs')->where('action', 'stock_adjustment.created')->where('auditable_id', $response->json('data.id'))->value('operation_id'))->toBe($response->json('data.operations.0.id'))
            ->and(DB::table('audit_logs')->where('action', 'inventory_operation.created')->where('operation_id', $response->json('data.operations.0.id'))->exists())->toBeTrue();
    });

    it('uses the movement code of the reason and direction', function (string $direction, string $reason, int $code, int $delta) {
        adjustmentPost($direction, $reason, [[$this->bolt, $this->a, 4]])
            ->assertCreated()
            ->assertJsonPath('data.operations.0.movements.0.movement_code', $code)
            ->assertJsonPath('data.operations.0.movements.0.delta', $delta);
    })->with([
        'found' => ['in', 'found', 712, 4],
        'positive correction' => ['in', 'correction', 712, 4],
        'damage' => ['out', 'damage', 551, -4],
        'loss' => ['out', 'loss', 711, -4],
        'negative correction' => ['out', 'correction', 711, -4],
    ]);

    it('admits a negative adjustment in a system location such as RECEPCION (REG-014)', function () {
        $receiving = $this->warehouse->locations()->where('system_role', SystemLocationRole::Receiving)->firstOrFail();
        adjustmentLoad($this->bolt, $receiving, 8);

        adjustmentPost('out', 'loss', [[$this->bolt, $receiving, 3]])->assertCreated();

        expect(adjustmentOnHand($this->bolt, $receiving))->toBe(5);
    });

    it('admits a blocked product, since adjustments serve to correct (REG-017)', function () {
        $this->bolt->forceFill(['status' => ProductStatus::Blocked])->save();

        adjustmentPost('out', 'damage', [[$this->bolt, $this->a, 1]])->assertCreated();
    });

    it('runs once and replays the stored response for the same key (ADR-007)', function () {
        $first = adjustmentPost('in', 'found', [[$this->bolt, $this->a, 10]], key: 'ajuste-1');
        $second = adjustmentPost('in', 'found', [[$this->bolt, $this->a, 10]], key: 'ajuste-1');

        $first->assertCreated();
        $second->assertCreated()->assertHeader(Idempotency::REPLAYED_HEADER, 'true');

        expect($second->json())->toEqual($first->json())
            ->and(StockAdjustment::query()->count())->toBe(1)
            ->and(adjustmentOnHand($this->bolt, $this->a))->toBe(510);
    });

    it('requires an Idempotency-Key (428)', function () {
        $this->postJson('/api/v1/inventory/adjustments', ['warehouse_id' => $this->warehouse->id])
            ->assertStatus(428)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
    });
});

describe('approval thresholds', function () {
    it('posts the items within their threshold and leaves the ones above it pending (202)', function () {
        // Sin fila (TOR-M8), dentro del umbral (TUE-M8) y por encima (MOT-1HP).
        adjustmentThresholds($this->nut, null, 50);
        adjustmentThresholds($this->motor, null, 2);

        $response = adjustmentPost('out', 'loss', [[$this->bolt, $this->a, 100], [$this->nut, $this->a, 50], [$this->motor, $this->a, 3]]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.items.*.status', ['posted', 'posted', 'pending'])
            ->assertJsonPath('data.items.*.approval_threshold', [null, 50, 2])
            ->assertJsonPath('data.items.2.operation_id', null)
            ->assertJsonPath('data.operations.0.movements.*.item_number', [1, 2]);

        expect($response->json('data.operations'))->toHaveCount(1)
            ->and(adjustmentOnHand($this->bolt, $this->a))->toBe(400)
            ->and(adjustmentOnHand($this->nut, $this->a))->toBe(150)
            ->and(adjustmentOnHand($this->motor, $this->a))->toBe(10);
    });

    it('creates the document without an operation when every item is above its threshold', function () {
        adjustmentThresholds($this->motor, 1, null);

        $response = adjustmentPost('in', 'found', [[$this->motor, $this->a, 5]]);

        $response->assertAccepted()
            ->assertJsonPath('data.items.0.status', 'pending')
            ->assertJsonPath('data.operations', []);

        expect(adjustmentOperations())->toBe(0)
            ->and(adjustmentOnHand($this->motor, $this->a))->toBe(10)
            ->and(DB::table('audit_logs')->where('action', 'stock_adjustment.created')->value('operation_id'))->toBeNull();
    });

    it('applies only the threshold of the direction of the adjustment', function () {
        adjustmentThresholds($this->motor, null, 1);

        adjustmentPost('in', 'found', [[$this->motor, $this->a, 5]])->assertCreated();
        adjustmentPost('out', 'loss', [[$this->motor, $this->a, 5]])->assertAccepted();
    });

    it('posts a quantity equal to the threshold, which does not exceed it', function () {
        adjustmentThresholds($this->motor, null, 3);

        adjustmentPost('out', 'damage', [[$this->motor, $this->a, 3]])->assertCreated();
    });

    it('rejects a pending item in an inactive warehouse', function () {
        adjustmentThresholds($this->motor, 1, null);
        $this->warehouse->forceFill(['is_active' => false])->save();

        adjustmentPost('in', 'found', [[$this->motor, $this->a, 5]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'WAREHOUSE_INACTIVE');

        expect(StockAdjustment::query()->count())->toBe(0);
    });
});

describe('business rules', function () {
    it('rejects a positive adjustment in a system location and creates nothing (REG-014)', function (SystemLocationRole $role) {
        $system = $this->warehouse->locations()->where('system_role', $role)->firstOrFail();

        adjustmentPost('in', 'found', [[$this->bolt, $this->a, 1], [$this->bolt, $system, 5]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'SYSTEM_LOCATION_NOT_ALLOWED')
            ->assertJsonPath('context.item', 2)
            ->assertJsonPath('context.system_role', $role->value);

        expect(StockAdjustment::query()->count())->toBe(0)->and(adjustmentOperations())->toBe(0);
    })->with(SystemLocationRole::cases());

    it('rolls back the whole document when an item has not enough free stock (INV-001, INV-004)', function () {
        adjustmentPost('out', 'loss', [[$this->bolt, $this->a, 10], [$this->nut, $this->a, 201]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INSUFFICIENT_STOCK');

        expect(StockAdjustment::query()->count())->toBe(0)
            ->and(DB::table('stock_adjustment_items')->count())->toBe(0)
            ->and(adjustmentOnHand($this->bolt, $this->a))->toBe(500);
    });

    it('rejects a blocked location (INV-009)', function () {
        $this->a->forceFill(['status' => LocationStatus::Blocked, 'status_reason' => 'Rack dañado'])->save();

        adjustmentPost('in', 'found', [[$this->bolt, $this->a, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'LOCATION_BLOCKED');
    });
});

describe('validation', function () {
    it('accepts only the reasons of the direction (INV-008)', function (string $direction, string $reason) {
        adjustmentPost($direction, $reason, [[$this->bolt, $this->a, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_code'], 'errors');
    })->with([
        'damage as positive' => ['in', 'damage'],
        'loss as positive' => ['in', 'loss'],
        'found as negative' => ['out', 'found'],
        'initial load, which has its own operation' => ['in', 'initial_load'],
        'unknown reason' => ['out', 'theft'],
    ]);

    it('rejects a location of another warehouse on its field', function () {
        $other = adjustmentLocation(Warehouse::factory()->create(), 'A-01-01');

        adjustmentPost('in', 'found', [[$this->bolt, $other, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.location_id'], 'errors');
    });

    it('rejects two items on the same stock row', function () {
        adjustmentPost('in', 'found', [[$this->bolt, $this->a, 1], [$this->nut, $this->b, 1], [$this->bolt, $this->a, 2]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.2'], 'errors');
    });

    it('rejects a malformed request', function (Closure $body, string $field) {
        $this->postJson('/api/v1/inventory/adjustments', $body->call($this), [Idempotency::HEADER => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'no direction' => [fn () => ['warehouse_id' => $this->warehouse->id, 'reason_code' => 'found', 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1]]], 'direction'],
        'unknown direction' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'up', 'reason_code' => 'found', 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1]]], 'direction'],
        'no items' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'in', 'reason_code' => 'found', 'items' => []], 'items'],
        'too many items' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'in', 'reason_code' => 'found', 'items' => array_fill(0, 101, ['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1])], 'items'],
        'zero quantity' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'in', 'reason_code' => 'found', 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 0]]], 'items.0.quantity'],
        'signed quantity' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'out', 'reason_code' => 'loss', 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => -3]]], 'items.0.quantity'],
        'unknown item field' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'in', 'reason_code' => 'found', 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1, 'lot' => 'X']]], 'items.0'],
        'unknown product' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'in', 'reason_code' => 'found', 'items' => [['product_id' => 999999, 'location_id' => $this->a->id, 'quantity' => 1]]], 'items.0.product_id'],
        'notes too long' => [fn () => ['warehouse_id' => $this->warehouse->id, 'direction' => 'in', 'reason_code' => 'found', 'notes' => str_repeat('x', 501), 'items' => [['product_id' => $this->bolt->id, 'location_id' => $this->a->id, 'quantity' => 1]]], 'notes'],
        'no warehouse' => [fn () => ['direction' => 'in', 'reason_code' => 'found', 'items' => []], 'warehouse_id'],
    ]);
});

describe('authorization (REG-005)', function () {
    it('forbids a warehouse the user does not operate in, without revealing whether it exists', function (Closure $warehouseId) {
        $this->postJson('/api/v1/inventory/adjustments', ['warehouse_id' => $warehouseId->call($this), 'items' => []], [Idempotency::HEADER => (string) Str::uuid()])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    })->with([
        'visible, not assigned' => [fn () => app(CreateWarehouse::class)('NORTE', 'Depósito norte')->id],
        'nonexistent' => [fn () => 999999],
    ]);

    it('forbids a role without inventory.adjust', function () {
        $this->actingAs(userWithAccess(Role::Operator, [$this->warehouse]));

        adjustmentPost('in', 'found', [[$this->bolt, $this->a, 1]])->assertForbidden();

        expect(StockAdjustment::query()->count())->toBe(0);
    });
});
