<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Actions\SetProductAdjustmentThresholds;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Data\AdjustmentItem;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Inventory\Exceptions\AdjustmentItemNotPending;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| Aprobación y rechazo de posiciones de ajuste (E3.23, REG-024, D13 del plan
| de E3): POST /inventory/adjustments/{id}/approve y /reject con los números
| de posición, y la consulta GET /inventory/adjustments y /{id}. Las ramas de
| error de StockLedger tienen su prueba en StockLedgerTest; acá se verifica
| que lleguen al aprobar, que la decisión sea todo o nada y quién decide.
*/

function approvalLocation(Warehouse $warehouse, string $code): Location
{
    $zone = $warehouse->zones()->firstOrCreate(['code' => 'ALM'], ['name' => 'Almacenamiento', 'type' => ZoneType::Storage]);

    return $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => $code]);
}

function approvalLoad(Product $product, Location $location, int $quantity): void
{
    DB::transaction(fn () => app(StockLedger::class)->apply(new OperationDraft(
        OperationType::AdjustmentIn,
        $location->warehouse_id,
        test()->creator->id,
        [new MovementLine($product->id, $location->id, 561, $quantity)],
        reasonCode: AdjustmentReason::InitialLoad,
    )));
}

function approvalOnHand(Product $product, Location $location): int
{
    return (int) Stock::query()->where('product_id', $product->id)->where('location_id', $location->id)->value('quantity_on_hand');
}

/**
 * Ajuste del creador de la prueba; con los umbrales del beforeEach, el
 * tornillo hasta 10 y la tuerca hasta 5 se contabilizan, y el resto queda
 * pendiente.
 *
 * @param  list<array{0: Product, 1: Location, 2: int}>  $items
 */
function approvalAdjustment(string $direction, array $items, ?Warehouse $warehouse = null, ?User $creator = null): StockAdjustment
{
    $direction = MovementDirection::from($direction);

    return app(AdjustStock::class)(
        ($warehouse ?? test()->warehouse)->id,
        $direction,
        $direction === MovementDirection::In ? AdjustmentReason::Found : AdjustmentReason::Loss,
        array_map(fn (array $item): AdjustmentItem => new AdjustmentItem($item[0]->id, $item[1]->id, $item[2]), $items),
        ($creator ?? test()->creator)->id,
    );
}

/**
 * @param  list<int>  $items
 * @param  array<string, mixed>  $extra
 */
function approvalPost(StockAdjustment $adjustment, string $decision, array $items, array $extra = [], ?string $key = null): TestResponse
{
    return test()->postJson(
        "/api/v1/inventory/adjustments/{$adjustment->id}/{$decision}",
        ['items' => $items, ...$extra],
        [Idempotency::HEADER => $key ?? (string) Str::uuid()],
    );
}

/**
 * @return list<string>
 */
function approvalStatuses(StockAdjustment $adjustment): array
{
    return $adjustment->items()->orderBy('item_number')->pluck('status')->map(fn (StockAdjustmentItemStatus $status): string => $status->value)->all();
}

function approvalOperations(StockAdjustment $adjustment): int
{
    return InventoryOperation::query()->where('reference_type', StockAdjustment::REFERENCE_TYPE)->where('reference_id', $adjustment->id)->count();
}

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->warehouse = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->a = approvalLocation($this->warehouse, 'A-01-01');
    $this->b = approvalLocation($this->warehouse, 'B-01-01');
    $this->bolt = Product::factory()->create(['sku' => 'TOR-M8']);
    $this->nut = Product::factory()->create(['sku' => 'TUE-M8']);
    $this->creator = userWithAccess(Role::Supervisor, [$this->warehouse]);
    $this->approver = userWithAccess(Role::Supervisor, [$this->warehouse]);

    foreach ([[$this->bolt, 10], [$this->nut, 5]] as [$product, $threshold]) {
        app(SetProductAdjustmentThresholds::class)($product, $this->warehouse, $threshold, $threshold);
    }

    approvalLoad($this->bolt, $this->a, 100);
    approvalLoad($this->bolt, $this->b, 100);
    approvalLoad($this->nut, $this->a, 50);
    $this->actingAs($this->approver);
});

/**
 * Salida con una posición contabilizada (1) y dos pendientes (2 y 3).
 */
function approvalMixedOut(): StockAdjustment
{
    $test = test();

    return approvalAdjustment('out', [[$test->bolt, $test->a, 5], [$test->bolt, $test->b, 30], [$test->nut, $test->a, 20]]);
}

describe('approve', function () {
    it('posts the approved items together in a new operation of the document (200)', function () {
        $adjustment = approvalMixedOut();

        $response = approvalPost($adjustment, 'approve', [3, 2], ['notes' => '  Rotura confirmada  ']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.items.*.status', ['posted', 'approved', 'approved'])
            ->assertJsonPath('data.items.1.decided_by', $this->approver->id)
            ->assertJsonPath('data.items.1.decision_notes', 'Rotura confirmada')
            ->assertJsonCount(2, 'data.operations')
            ->assertJsonPath('data.operations.1.type', 'adjustment_out')
            ->assertJsonPath('data.operations.1.reason_code', 'loss')
            ->assertJsonPath('data.operations.1.performed_by', $this->approver->id)
            ->assertJsonPath('data.operations.1.notes', 'Rotura confirmada')
            ->assertJsonPath('data.operations.1.movements.*.item_number', [2, 3])
            ->assertJsonPath('data.operations.1.movements.*.delta', [-30, -20]);

        $operation = InventoryOperation::query()->where('reference_id', $adjustment->id)->latest('id')->firstOrFail();

        expect(approvalOnHand($this->bolt, $this->a))->toBe(95)
            ->and(approvalOnHand($this->bolt, $this->b))->toBe(70)
            ->and(approvalOnHand($this->nut, $this->a))->toBe(30)
            ->and($adjustment->items()->where('item_number', '>', 1)->pluck('operation_id')->unique()->values()->all())->toBe([$operation->id])
            ->and($response->json('data.items.2.operation_number'))->toBe($operation->formattedNumber());

        $audit = AuditLog::query()->where('action', 'stock_adjustment.items_approved')->sole();

        expect($audit->auditable_id)->toBe($adjustment->id)
            ->and($audit->user_id)->toBe($this->approver->id)
            ->and($audit->warehouse_id)->toBe($this->warehouse->id)
            ->and($audit->operation_id)->toBe($operation->id)
            ->and($audit->before)->toBe(['items' => [2, 3], 'status' => 'pending'])
            ->and($audit->after)->toMatchArray(['items' => [2, 3], 'status' => 'approved', 'operation_id' => $operation->id]);
    });

    it('decides only the requested items and keeps the document pending', function () {
        $adjustment = approvalMixedOut();

        approvalPost($adjustment, 'approve', [3])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.items.*.status', ['posted', 'pending', 'approved'])
            ->assertJsonPath('data.items.2.decision_notes', null);

        expect(approvalOnHand($this->bolt, $this->b))->toBe(100)->and(approvalOperations($adjustment))->toBe(2);
    });

    it('posts a positive adjustment', function () {
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);

        approvalPost($adjustment, 'approve', [1])
            ->assertOk()
            ->assertJsonPath('data.operations.0.type', 'adjustment_in')
            ->assertJsonPath('data.operations.0.reason_code', 'found')
            ->assertJsonPath('data.operations.0.movements.0.delta', 40);

        expect(approvalOnHand($this->bolt, $this->a))->toBe(140);
    });

    it('keeps every item pending when the stock no longer covers one of them (INSUFFICIENT_STOCK, all or nothing)', function () {
        $adjustment = approvalAdjustment('out', [[$this->bolt, $this->b, 30], [$this->nut, $this->a, 20]]);
        // Otro ajuste se lleva la tuerca entre la solicitud y la aprobación.
        $other = approvalAdjustment('out', [[$this->nut, $this->a, 40]]);
        approvalPost($other, 'approve', [1])->assertOk();

        approvalPost($adjustment, 'approve', [1, 2])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('context.item', 2)
            ->assertJsonPath('context.quantity_available', 10);

        expect(approvalStatuses($adjustment))->toBe(['pending', 'pending'])
            ->and(approvalOperations($adjustment))->toBe(0)
            ->and(approvalOnHand($this->bolt, $this->b))->toBe(100);
    });

    it('does not compare the threshold again', function () {
        $adjustment = approvalAdjustment('out', [[$this->bolt, $this->b, 30]]);
        app(SetProductAdjustmentThresholds::class)($this->bolt, $this->warehouse, 50, 50);

        approvalPost($adjustment, 'approve', [1])->assertOk()->assertJsonPath('data.items.0.approval_threshold', 10);
    });

    it('answers WAREHOUSE_INACTIVE when the warehouse was deactivated', function () {
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);
        DB::table('warehouses')->where('id', $this->warehouse->id)->update(['is_active' => false]);

        approvalPost($adjustment, 'approve', [1])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'WAREHOUSE_INACTIVE');

        expect(approvalStatuses($adjustment))->toBe(['pending']);
    });

    it('replays an approval with the same key without posting twice (ADR-007)', function () {
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);
        $key = (string) Str::uuid();

        $first = approvalPost($adjustment, 'approve', [1], key: $key)->assertOk();
        $second = approvalPost($adjustment, 'approve', [1], key: $key)->assertOk();

        expect($second->headers->get(Idempotency::REPLAYED_HEADER))->toBe('true')
            ->and($second->json())->toEqual($first->json())
            ->and(approvalOperations($adjustment))->toBe(1)
            ->and(approvalOnHand($this->bolt, $this->a))->toBe(140);
    });
});

describe('reject', function () {
    it('rejects the items with the reason and moves no stock (200)', function () {
        $adjustment = approvalMixedOut();

        approvalPost($adjustment, 'reject', [2, 3], ['notes' => '  Conteo mal hecho  '])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.items.*.status', ['posted', 'rejected', 'rejected'])
            ->assertJsonPath('data.items.*.decision_notes', [null, 'Conteo mal hecho', 'Conteo mal hecho'])
            ->assertJsonPath('data.items.1.decided_by', $this->approver->id)
            ->assertJsonPath('data.items.1.operation_id', null)
            ->assertJsonCount(1, 'data.operations');

        $audit = AuditLog::query()->where('action', 'stock_adjustment.items_rejected')->sole();

        expect(approvalOnHand($this->bolt, $this->b))->toBe(100)
            ->and(approvalOnHand($this->nut, $this->a))->toBe(50)
            ->and($audit->operation_id)->toBeNull()
            ->and($audit->after)->toBe(['items' => [2, 3], 'status' => 'rejected', 'decision_notes' => 'Conteo mal hecho']);
    });

    it('requires a reason', function (?string $notes) {
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);

        approvalPost($adjustment, 'reject', [1], $notes === null ? [] : ['notes' => $notes])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors('notes');

        expect(approvalStatuses($adjustment))->toBe(['pending']);
    })->with([null, '', '   ']);

    it('rejects in an inactive warehouse', function () {
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);
        DB::table('warehouses')->where('id', $this->warehouse->id)->update(['is_active' => false]);

        approvalPost($adjustment, 'reject', [1], ['notes' => 'Depósito cerrado'])->assertOk();

        expect(approvalStatuses($adjustment))->toBe(['rejected']);
    });
});

describe('decision rules', function () {
    it('does not let the creator decide on the document, even as administrator (SELF_APPROVAL_NOT_ALLOWED)', function (string $decision) {
        $admin = userWithAccess(Role::Administrator, [$this->warehouse]);
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]], creator: $admin);

        $this->actingAs($admin);

        approvalPost($adjustment, $decision, [1], ['notes' => 'Propio'])
            ->assertForbidden()
            ->assertJsonPath('code', 'SELF_APPROVAL_NOT_ALLOWED');

        expect(approvalStatuses($adjustment))->toBe(['pending']);
    })->with(['approve', 'reject']);

    it('answers ADJUSTMENT_ITEM_NOT_PENDING for an item already decided or posted, and decides none (409)', function (string $decision, int $decided, string $status) {
        $adjustment = approvalMixedOut();
        approvalPost($adjustment, 'reject', [2], ['notes' => 'Primera decisión'])->assertOk();

        approvalPost($adjustment, $decision, [3, $decided], ['notes' => 'Segunda decisión'])
            ->assertConflict()
            ->assertJsonPath('code', 'ADJUSTMENT_ITEM_NOT_PENDING')
            ->assertJsonPath('context.item', $decided)
            ->assertJsonPath('context.status', $status);

        expect(approvalStatuses($adjustment))->toBe(['posted', 'rejected', 'pending'])
            ->and(approvalOperations($adjustment))->toBe(1);
    })->with([
        'approve a rejected item' => ['approve', 2, 'rejected'],
        'reject a rejected item' => ['reject', 2, 'rejected'],
        'approve a posted item' => ['approve', 1, 'posted'],
    ]);

    it('translates the trigger of the item transition when the application check is bypassed', function () {
        $adjustment = approvalAdjustment('out', [[$this->bolt, $this->a, 4]]);

        try {
            DB::table('stock_adjustment_items')->where('adjustment_id', $adjustment->id)->update([
                'status' => 'rejected', 'decided_by' => $this->approver->id, 'decided_at' => now(), 'decision_notes' => 'x',
            ]);
            $this->fail('El trigger debía rechazar la transición.');
        } catch (QueryException $exception) {
            expect(app(DatabaseErrorTranslator::class)->translate($exception))->toBeInstanceOf(AdjustmentItemNotPending::class);
        }
    });

    it('validates the item numbers against the document', function (array $items, string $field) {
        $adjustment = approvalMixedOut();

        approvalPost($adjustment, 'approve', $items)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors($field);
    })->with([
        'empty' => [[], 'items'],
        'unknown item' => [[2, 9], 'items.1'],
        'repeated item' => [[2, 2], 'items.0'],
        'not a number' => [['dos'], 'items.0'],
    ]);

    it('rejects an item number of another document', function () {
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);
        approvalMixedOut();

        approvalPost($adjustment, 'approve', [2])->assertUnprocessable()->assertJsonValidationErrors('items.0');
    });
});

describe('authorization', function () {
    it('answers 404 for a document outside the view scope and for an unknown one', function () {
        $other = app(CreateWarehouse::class)('NORTE', 'Depósito norte');
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);

        // Sin warehouses.view_all, que tienen todos los roles.
        $outsider = userWithAccess(null, [$other]);
        $outsider->givePermissionTo('inventory.adjust.approve');

        $this->actingAs($outsider);

        approvalPost($adjustment, 'approve', [1])->assertNotFound();
        approvalPost($adjustment, 'reject', [1], ['notes' => 'x'])->assertNotFound();
        $this->getJson("/api/v1/inventory/adjustments/{$adjustment->id}")->assertNotFound();
        $this->getJson('/api/v1/inventory/adjustments/999999999')->assertNotFound();
        $this->getJson('/api/v1/inventory/adjustments/abc')->assertNotFound();
    });

    it('answers 403 to a user who sees the warehouse but does not operate in it', function () {
        $viewer = userWithAccess(Role::Supervisor);
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);

        $this->actingAs($viewer);

        approvalPost($adjustment, 'approve', [1])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson("/api/v1/inventory/adjustments/{$adjustment->id}")->assertOk();
    });

    it('answers 403 without the adjustment permissions', function () {
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);

        $this->actingAs(userWithAccess(Role::Operator, [$this->warehouse]));

        approvalPost($adjustment, 'approve', [1])->assertForbidden();
        $this->getJson("/api/v1/inventory/adjustments/{$adjustment->id}")->assertForbidden();
        $this->getJson('/api/v1/inventory/adjustments')->assertForbidden();
    });

    it('lets a user with only inventory.adjust consult the documents but not decide', function () {
        $adjuster = userWithAccess(null, [$this->warehouse]);
        $adjuster->givePermissionTo('inventory.adjust');
        $adjustment = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);

        $this->actingAs($adjuster);

        $this->getJson("/api/v1/inventory/adjustments/{$adjustment->id}")->assertOk();
        $this->getJson('/api/v1/inventory/adjustments')->assertOk()->assertJsonCount(1, 'data');
        approvalPost($adjustment, 'approve', [1])->assertForbidden();
    });
});

describe('consultation', function () {
    it('shows the document with its items and operations', function () {
        $adjustment = approvalMixedOut();

        $this->getJson("/api/v1/inventory/adjustments/{$adjustment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $adjustment->id)
            ->assertJsonPath('data.number', $adjustment->formattedNumber())
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.items.*.item_number', [1, 2, 3])
            ->assertJsonPath('data.items.*.approval_threshold', [10, 10, 5])
            ->assertJsonCount(1, 'data.operations');
    });

    it('lists the documents with pending items by default', function () {
        $pending = approvalMixedOut();
        $completed = approvalAdjustment('out', [[$this->bolt, $this->a, 2]]);

        $this->getJson('/api/v1/inventory/adjustments')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$pending->id])
            ->assertJsonPath('data.0.items.*.status', ['posted', 'pending', 'pending'])
            ->assertJsonMissingPath('data.0.operations')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/inventory/adjustments?filter[status]=')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$completed->id, $pending->id]);

        $this->getJson('/api/v1/inventory/adjustments?filter[status]=posted&sort=number')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$pending->id, $completed->id]);
    });

    it('filters by warehouse, product and requester within the view scope', function () {
        $other = app(CreateWarehouse::class)('NORTE', 'Depósito norte');
        $north = approvalLocation($other, 'N-01-01');
        app(SetProductAdjustmentThresholds::class)($this->bolt, $other, 10, 10);
        approvalAdjustment('in', [[$this->bolt, $north, 40]], $other, userWithAccess(Role::Supervisor, [$other]));
        $bolts = approvalAdjustment('in', [[$this->bolt, $this->a, 40]]);
        $nuts = approvalAdjustment('in', [[$this->nut, $this->a, 40]], creator: $this->approver);
        $scoped = userWithAccess(null, [$this->warehouse]);
        $scoped->givePermissionTo('inventory.adjust.approve');

        $this->actingAs($scoped);

        $this->getJson('/api/v1/inventory/adjustments')->assertOk()->assertJsonPath('data.*.id', [$nuts->id, $bolts->id]);
        $this->getJson("/api/v1/inventory/adjustments?filter[product_id]={$this->nut->id}")->assertOk()->assertJsonPath('data.*.id', [$nuts->id]);
        $this->getJson("/api/v1/inventory/adjustments?filter[created_by]={$this->creator->id}")->assertOk()->assertJsonPath('data.*.id', [$bolts->id]);
        $this->getJson("/api/v1/inventory/adjustments?filter[warehouse_id]={$other->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/inventory/adjustments?filter[warehouse_id]={$this->warehouse->id}")->assertOk()->assertJsonCount(2, 'data');
    });

    it('rejects an unknown status', function () {
        $this->getJson('/api/v1/inventory/adjustments?filter[status]=open')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.status');
    });
});
