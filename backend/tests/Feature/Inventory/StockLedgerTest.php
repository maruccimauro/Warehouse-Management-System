<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Exceptions\LocationBlocked;
use App\Modules\Inventory\Exceptions\LocationCapacityExceeded;
use App\Modules\Inventory\Exceptions\ProductNotOperable;
use App\Modules\Inventory\Exceptions\ReservedStockNotAvailable;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Exceptions\WarehouseInactive;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordingAuditLogger;

/*
| StockLedger (E3.03): el algoritmo de contabilización, un caso por rama de
| error. La concurrencia real (procesos en paralelo) llega con E3.14.
*/

/**
 * Contabiliza el borrador en una transacción, como lo hace una Action.
 */
function ledgerPost(OperationDraft $draft): InventoryOperation
{
    return DB::transaction(fn (): InventoryOperation => app(StockLedger::class)->apply($draft));
}

/**
 * Contabiliza un borrador que debe fallar y devuelve la excepción.
 */
function ledgerRejection(OperationDraft $draft): Throwable
{
    try {
        ledgerPost($draft);
    } catch (Throwable $exception) {
        return $exception;
    }

    throw new RuntimeException('La operación no falló.');
}

/**
 * @param  list<MovementLine>  $lines
 * @param  array<string, mixed>  $options
 */
function ledgerDraft(OperationType $type, array $lines, array $options = []): OperationDraft
{
    $test = test();

    return new OperationDraft(
        type: $type,
        warehouseId: $options['warehouse_id'] ?? $test->warehouse->id,
        performedBy: $test->user->id,
        lines: $lines,
        reasonCode: $options['reason'] ?? null,
        referenceType: $options['reference_type'] ?? null,
        referenceId: $options['reference_id'] ?? null,
        reversesOperationId: $options['reverses'] ?? null,
        notes: $options['notes'] ?? null,
    );
}

function ledgerLoad(Product $product, Location $location, int $quantity): InventoryOperation
{
    return ledgerPost(ledgerDraft(OperationType::AdjustmentIn, [
        new MovementLine($product->id, $location->id, 561, $quantity),
    ], ['reason' => AdjustmentReason::InitialLoad]));
}

function ledgerTransfer(Product $product, Location $from, Location $to, int $quantity): InventoryOperation
{
    return ledgerPost(ledgerDraft(OperationType::Transfer, [
        new MovementLine($product->id, $from->id, 931, -$quantity),
        new MovementLine($product->id, $to->id, 931, $quantity),
    ]));
}

function ledgerRemoval(Product $product, Location $location, int $quantity): OperationDraft
{
    return ledgerDraft(OperationType::AdjustmentOut, [
        new MovementLine($product->id, $location->id, 711, -$quantity),
    ], ['reason' => AdjustmentReason::Loss]);
}

function ledgerOnHand(Product $product, Location $location): int
{
    return (int) Stock::query()->where('product_id', $product->id)->where('location_id', $location->id)->value('quantity_on_hand');
}

function ledgerLocation(Warehouse $warehouse, string $code, ?int $maxUnits = null): Location
{
    $zone = $warehouse->zones()->firstOrCreate(['code' => 'ALM'], ['name' => 'Almacenamiento', 'type' => ZoneType::Storage]);

    return $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => $code, 'max_units' => $maxUnits]);
}

beforeEach(function () {
    $this->audit = new RecordingAuditLogger;
    app()->instance(AuditLogger::class, $this->audit);

    $this->user = User::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->a = ledgerLocation($this->warehouse, 'A-01-01');
    $this->b = ledgerLocation($this->warehouse, 'B-01-01');
    $this->product = Product::factory()->create();
});

describe('posting', function () {
    it('posts a transfer as one operation with a movement per stock row and its balance', function () {
        ledgerLoad($this->product, $this->a, 10);

        $operation = ledgerTransfer($this->product, $this->a, $this->b, 4);

        expect($operation->number)->toBeGreaterThanOrEqual(4900000000)->toBeLessThanOrEqual(4999999999)
            ->and($operation->type)->toBe(OperationType::Transfer)
            ->and($operation->movements->map(fn (StockMovement $line): array => [$line->movement_code, $line->delta, $line->balance_after])->all())
            ->toBe([[931, -4, 6], [931, 4, 4]])
            ->and(ledgerOnHand($this->product, $this->a))->toBe(6)
            ->and(ledgerOnHand($this->product, $this->b))->toBe(4)
            ->and(Stock::query()->where('location_id', $this->b->id)->value('last_movement_at'))->not->toBeNull();
    });

    it('creates a missing stock row once and reuses it afterwards', function () {
        ledgerLoad($this->product, $this->a, 3);
        ledgerLoad(Product::factory()->create(), $this->a, 2);
        ledgerLoad($this->product, $this->a, 5);

        expect(Stock::query()->where('location_id', $this->a->id)->count())->toBe(2)
            ->and(ledgerOnHand($this->product, $this->a))->toBe(8);
    });

    it('audits the operation inside its transaction (INV-012)', function () {
        $operation = ledgerLoad($this->product, $this->a, 3);

        $record = collect($this->audit->records)->firstWhere('action', 'inventory_operation.created');

        expect($record)->not->toBeNull()
            ->and($record['auditable']->is($operation))->toBeTrue()
            ->and($record['warehouse_id'])->toBe($this->warehouse->id)
            ->and($record['after'])->toMatchArray(['number' => $operation->number, 'type' => 'adjustment_in', 'reason_code' => 'initial_load', 'lines' => 1])
            ->and($record['transaction_level'])->toBeGreaterThan(0);
    });
});

describe('draft validation', function () {
    it('rejects a malformed draft as a programming error', function (Closure $draft, string $message) {
        $error = ledgerRejection($draft->call($this));

        expect($error)->toBeInstanceOf(LogicException::class)
            ->and($error->getMessage())->toContain($message)
            ->and(InventoryOperation::query()->count())->toBe(0);
    })->with([
        'no lines' => [fn () => ledgerDraft(OperationType::Transfer, []), 'al menos una línea'],
        'zero delta' => [fn () => ledgerDraft(OperationType::Transfer, [new MovementLine($this->product->id, $this->a->id, 931, 0)]), 'distinta de cero'],
        'code of another type' => [fn () => ledgerDraft(OperationType::Transfer, [new MovementLine($this->product->id, $this->a->id, 101, 5)]), 'no corresponde'],
        'code with the wrong sign' => [fn () => ledgerDraft(OperationType::AdjustmentIn, [new MovementLine($this->product->id, $this->a->id, 561, -5)], ['reason' => AdjustmentReason::InitialLoad]), 'no corresponde'],
        'reason without a code for the type' => [fn () => ledgerDraft(OperationType::AdjustmentIn, [new MovementLine($this->product->id, $this->a->id, 712, 5)], ['reason' => AdjustmentReason::Damage]), 'no corresponde'],
        'adjustment without reason' => [fn () => ledgerDraft(OperationType::AdjustmentIn, [new MovementLine($this->product->id, $this->a->id, 712, 5)]), 'motivo'],
        'reason outside an adjustment' => [fn () => ledgerDraft(OperationType::Transfer, [new MovementLine($this->product->id, $this->a->id, 931, 5)], ['reason' => AdjustmentReason::Found]), 'motivo'],
        'reversal without the reversed operation' => [fn () => ledgerDraft(OperationType::Reversal, [new MovementLine($this->product->id, $this->a->id, 932, 5, reversesMovementId: 1)], ['notes' => 'x']), 'revierte'],
        'reversal line without its original' => [fn () => ledgerDraft(OperationType::Reversal, [new MovementLine($this->product->id, $this->a->id, 932, 5)], ['reverses' => 1, 'notes' => 'x']), 'línea original'],
        'same stock row twice' => [fn () => ledgerDraft(OperationType::Transfer, [new MovementLine($this->product->id, $this->a->id, 931, -5), new MovementLine($this->product->id, $this->a->id, 931, 5)]), 'una sola línea'],
        'reservation on an entry' => [fn () => ledgerDraft(OperationType::Transfer, [new MovementLine($this->product->id, $this->a->id, 931, 5, reservationId: 1)]), 'reserva'],
    ]);
});

describe('warehouse and item state', function () {
    it('rejects an operation in an inactive warehouse (REG-015)', function () {
        $this->warehouse->forceFill(['is_active' => false])->save();

        expect(ledgerRejection(ledgerRemoval($this->product, $this->a, 1)))->toBeInstanceOf(WarehouseInactive::class);
    });

    it('rejects a transfer of a blocked item with its status in the context (REG-017)', function () {
        ledgerLoad($this->product, $this->a, 5);
        $this->product->forceFill(['status' => ProductStatus::Blocked])->save();

        $error = ledgerRejection(ledgerDraft(OperationType::Transfer, [
            new MovementLine($this->product->id, $this->a->id, 931, -2),
            new MovementLine($this->product->id, $this->b->id, 931, 2),
        ]));

        expect($error)->toBeInstanceOf(ProductNotOperable::class)
            ->and($error->errorCode())->toBe('PRODUCT_NOT_OPERABLE')
            ->and($error->context())->toMatchArray(['product_id' => $this->product->id, 'status' => 'blocked', 'operation_type' => 'transfer']);
    });

    it('lets a blocked item be adjusted and reversed, and a discontinued one be moved', function () {
        $loaded = ledgerLoad($this->product, $this->a, 5);
        $this->product->forceFill(['status' => ProductStatus::Blocked])->save();

        ledgerPost(ledgerRemoval($this->product, $this->a, 1));
        ledgerPost(ledgerDraft(OperationType::Reversal, [
            new MovementLine($this->product->id, $this->a->id, 562, -2, reversesMovementId: $loaded->movements->first()?->id),
        ], ['reverses' => $loaded->id, 'notes' => 'Carga duplicada']));

        $this->product->forceFill(['status' => ProductStatus::Discontinued])->save();
        ledgerTransfer($this->product, $this->a, $this->b, 1);

        expect(ledgerOnHand($this->product, $this->a))->toBe(1)
            ->and(ledgerOnHand($this->product, $this->b))->toBe(1);
    });
});

describe('locations', function () {
    it('rejects a movement on a location that is not active (INV-009)', function (LocationStatus $status) {
        ledgerLoad($this->product, $this->a, 5);
        $this->b->forceFill(['status' => $status, 'status_reason' => 'Revisión'])->save();

        $error = ledgerRejection(ledgerDraft(OperationType::Transfer, [
            new MovementLine($this->product->id, $this->a->id, 931, -2),
            new MovementLine($this->product->id, $this->b->id, 931, 2),
        ]));

        expect($error)->toBeInstanceOf(LocationBlocked::class)
            ->and($error->errorCode())->toBe('LOCATION_BLOCKED')
            ->and($error->context())->toBe(['location_id' => $this->b->id, 'status' => $status->value])
            ->and(ledgerOnHand($this->product, $this->a))->toBe(5);
    })->with([LocationStatus::Blocked, LocationStatus::Counting, LocationStatus::Inactive]);

    it('rejects an entry that leaves more units than max_units, counting every item (REG-010)', function () {
        $small = ledgerLocation($this->warehouse, 'C-01-01', maxUnits: 10);
        ledgerLoad(Product::factory()->create(), $small, 6);
        ledgerLoad($this->product, $small, 3);

        $error = ledgerRejection(ledgerDraft(OperationType::AdjustmentIn, [
            new MovementLine($this->product->id, $small->id, 712, 2),
        ], ['reason' => AdjustmentReason::Found]));

        expect($error)->toBeInstanceOf(LocationCapacityExceeded::class)
            ->and($error->errorCode())->toBe('LOCATION_CAPACITY_EXCEEDED')
            ->and($error->context())->toBe(['location_id' => $small->id, 'max_units' => 10, 'current_units' => 9, 'incoming_units' => 2])
            ->and(ledgerOnHand($this->product, $small))->toBe(3);
    });

    it('accepts an entry up to max_units and a withdrawal from a full location', function () {
        $small = ledgerLocation($this->warehouse, 'C-01-01', maxUnits: 10);
        ledgerLoad($this->product, $small, 10);

        ledgerTransfer($this->product, $small, $this->a, 4);

        expect(ledgerOnHand($this->product, $small))->toBe(6);
    });

    it('does not check the capacity on a reversal, which returns goods that never left', function () {
        $small = ledgerLocation($this->warehouse, 'C-01-01', maxUnits: 10);
        ledgerLoad($this->product, $small, 10);
        $moved = ledgerTransfer($this->product, $small, $this->a, 5);
        ledgerLoad($this->product, $small, 5);

        ledgerPost(ledgerDraft(OperationType::Reversal, $moved->movements->map(fn (StockMovement $line): MovementLine => new MovementLine(
            $line->product_id, $line->location_id, 932, -$line->delta, reversesMovementId: $line->id,
        ))->all(), ['reverses' => $moved->id, 'notes' => 'Transferencia no realizada']));

        expect(ledgerOnHand($this->product, $small))->toBe(15)
            ->and(ledgerOnHand($this->product, $this->a))->toBe(0);
    });
});

describe('availability (INV-001, INV-005)', function () {
    beforeEach(function () {
        ledgerLoad($this->product, $this->a, 10);
        $this->stock = Stock::query()->where('location_id', $this->a->id)->firstOrFail();
    });

    it('rejects a withdrawal above the stock without changes', function () {
        $error = ledgerRejection(ledgerRemoval($this->product, $this->a, 11));

        expect($error)->toBeInstanceOf(InsufficientStock::class)
            ->and($error->errorCode())->toBe('INSUFFICIENT_STOCK')
            ->and($error->context())->toMatchArray(['requested' => 11, 'quantity_on_hand' => 10, 'quantity_available' => 10])
            ->and(ledgerOnHand($this->product, $this->a))->toBe(10)
            ->and(InventoryOperation::query()->count())->toBe(1);
    });

    it('tells reserved stock apart from missing stock', function () {
        DB::table('stocks')->where('id', $this->stock->id)->update(['quantity_reserved' => 4, 'quantity_blocked' => 1]);

        $reserved = ledgerRejection(ledgerRemoval($this->product, $this->a, 7));
        $missing = ledgerRejection(ledgerRemoval($this->product, $this->a, 10));

        expect($reserved)->toBeInstanceOf(ReservedStockNotAvailable::class)
            ->and($reserved->errorCode())->toBe('RESERVED_STOCK_NOT_AVAILABLE')
            ->and($reserved->context())->toMatchArray(['requested' => 7, 'quantity_reserved' => 4, 'quantity_blocked' => 1, 'quantity_available' => 5])
            ->and($missing)->toBeInstanceOf(InsufficientStock::class);

        ledgerPost(ledgerRemoval($this->product, $this->a, 5));

        expect(ledgerOnHand($this->product, $this->a))->toBe(5);
    });

    it('consumes a reservation whole: takes what is removed and releases the rest', function () {
        DB::table('stocks')->where('id', $this->stock->id)->update(['quantity_reserved' => 6]);
        $reservation = StockReservation::query()->create([
            'stock_id' => $this->stock->id,
            'reference_type' => 'customer_order_item',
            'reference_id' => 1,
            'quantity' => 6,
            'created_by' => $this->user->id,
        ]);

        $operation = ledgerPost(ledgerDraft(OperationType::Dispatch, [
            new MovementLine($this->product->id, $this->a->id, 601, -4, reservationId: $reservation->id),
        ], ['reference_type' => 'dispatch', 'reference_id' => 1]));

        $reservation->refresh();
        $this->stock->refresh();

        expect($reservation->status)->toBe(ReservationStatus::Consumed)
            ->and($reservation->quantity_consumed)->toBe(4)
            ->and($reservation->consumed_by_operation_id)->toBe($operation->id)
            ->and($operation->movements->first()?->reservation_id)->toBe($reservation->id)
            ->and($this->stock->quantity_on_hand)->toBe(6)
            ->and($this->stock->quantity_reserved)->toBe(0);
    });

    it('rejects a reservation that does not cover the withdrawal or is not active', function (ReservationStatus $status, int $quantity) {
        $reservation = StockReservation::query()->create([
            'stock_id' => $this->stock->id,
            'reference_type' => 'customer_order_item',
            'reference_id' => 1,
            'quantity' => 3,
            'status' => $status,
            'resolved_at' => $status === ReservationStatus::Active ? null : now(),
            'created_by' => $this->user->id,
        ]);

        $error = ledgerRejection(ledgerDraft(OperationType::Dispatch, [
            new MovementLine($this->product->id, $this->a->id, 601, -$quantity, reservationId: $reservation->id),
        ], ['reference_type' => 'dispatch', 'reference_id' => 1]));

        expect($error)->toBeInstanceOf(LogicException::class)
            ->and($error->getMessage())->toContain('no cubre el retiro');
    })->with([
        'above the reservation' => [ReservationStatus::Active, 4],
        'released' => [ReservationStatus::Released, 2],
    ]);

    it('rejects a reversal that would leave negative stock', function () {
        $moved = ledgerTransfer($this->product, $this->a, $this->b, 10);
        ledgerPost(ledgerRemoval($this->product, $this->b, 10));

        $error = ledgerRejection(ledgerDraft(OperationType::Reversal, $moved->movements->map(fn (StockMovement $line): MovementLine => new MovementLine(
            $line->product_id, $line->location_id, 932, -$line->delta, reversesMovementId: $line->id,
        ))->all(), ['reverses' => $moved->id, 'notes' => 'Transferencia no realizada']));

        expect($error)->toBeInstanceOf(InsufficientStock::class)
            ->and($error)->toBeInstanceOf(DomainException::class);
    });
});

describe('reversals', function () {
    it('reverses part of a line and inserts the lines in ascending order of the original', function () {
        ledgerLoad($this->product, $this->a, 10);
        $moved = ledgerTransfer($this->product, $this->a, $this->b, 10);
        $lines = $moved->movements->sortByDesc('id')->values();

        $reversal = ledgerPost(ledgerDraft(OperationType::Reversal, $lines->map(fn (StockMovement $line): MovementLine => new MovementLine(
            $line->product_id, $line->location_id, 932, $line->delta > 0 ? -4 : 4, reversesMovementId: $line->id,
        ))->all(), ['reverses' => $moved->id, 'notes' => 'Se transfirieron 6']));

        expect($reversal->movements->pluck('reverses_movement_id')->all())->toBe($moved->movements->pluck('id')->sort()->values()->all())
            ->and(ledgerOnHand($this->product, $this->a))->toBe(4)
            ->and(ledgerOnHand($this->product, $this->b))->toBe(6)
            ->and($reversal->reverses_operation_id)->toBe($moved->id);
    });
});

describe('single write path (ADR-002)', function () {
    it('rejects writing a stock row or a movement outside StockLedger', function (Closure $write) {
        ledgerLoad($this->product, $this->a, 1);

        $write->call($this);
    })->with([
        'stock save' => [fn () => Stock::query()->firstOrFail()->forceFill(['quantity_on_hand' => 99])->save()],
        'stock create' => [fn () => Stock::query()->create(['warehouse_id' => $this->warehouse->id, 'location_id' => $this->b->id, 'product_id' => $this->product->id])],
        'movement create' => [fn () => StockMovement::query()->create(['operation_id' => 1])],
    ])->throws(LogicException::class, 'solo desde StockLedger');
});
