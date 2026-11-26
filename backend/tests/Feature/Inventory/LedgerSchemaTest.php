<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Exceptions\ReversalExceedsRemaining;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Database\DatabaseErrorTranslator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Esquema del libro de inventario (E3.02): lo que la base garantiza por sí
| sola, sin StockLedger. Las filas de stocks y stock_movements se escriben con
| DB::table, porque los modelos solo se graban desde StockLedger. Cada
| sentencia que debe fallar corre en un punto de guardado, para que el error
| no aborte la transacción de la prueba.
*/

/**
 * Ejecuta la sentencia en un punto de guardado y devuelve el error de la base.
 */
function databaseErrorOf(Closure $statement): QueryException
{
    try {
        DB::transaction($statement);
    } catch (QueryException $exception) {
        return $exception;
    }

    throw new RuntimeException('La sentencia no falló.');
}

function storageLocation(Warehouse $warehouse, string $code = 'A-01-01'): Location
{
    $zone = $warehouse->zones()->create(['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => ZoneType::Storage]);

    return $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => $code]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function ledgerOperation(Warehouse $warehouse, User $user, OperationType $type, array $attributes = []): InventoryOperation
{
    return InventoryOperation::query()->create([
        'number' => InventoryOperation::nextNumber(),
        'type' => $type,
        'warehouse_id' => $warehouse->id,
        'performed_by' => $user->id,
        'performed_at' => now(),
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function ledgerMovement(InventoryOperation $operation, Stock $stock, int $code, int $delta, array $attributes = []): StockMovement
{
    return StockMovement::query()->findOrFail(DB::table('stock_movements')->insertGetId([
        'operation_id' => $operation->id,
        // Una posición por línea, salvo que la prueba indique otra.
        'item_number' => DB::table('stock_movements')->where('operation_id', $operation->id)->count() + 1,
        'warehouse_id' => $stock->warehouse_id,
        'stock_id' => $stock->id,
        'product_id' => $stock->product_id,
        'location_id' => $stock->location_id,
        'movement_code' => $code,
        'delta' => $delta,
        'balance_after' => max(0, $delta),
        'created_at' => now(),
        ...$attributes,
    ]));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function insertStock(array $attributes): int
{
    return DB::table('stocks')->insertGetId($attributes);
}

/**
 * Otra existencia del mismo artículo, en una ubicación nueva de la misma zona.
 */
function secondStockRow(Stock $stock): Stock
{
    $location = Location::query()->findOrFail($stock->location_id);
    $other = $location->warehouse->locations()->create(['zone_id' => $location->zone_id, 'code' => 'B-01-01']);

    return Stock::query()->findOrFail(insertStock(['warehouse_id' => $stock->warehouse_id, 'location_id' => $other->id, 'product_id' => $stock->product_id]));
}

beforeEach(function () {
    // Las operaciones de estas pruebas se insertan con DB::table, sin StockLedger.
    skipAuditedOperationsCheck();

    $this->user = User::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->location = storageLocation($this->warehouse);
    $this->product = Product::factory()->create();
    $this->stock = Stock::query()->findOrFail(insertStock([
        'warehouse_id' => $this->warehouse->id,
        'location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity_on_hand' => 10,
    ]));
});

describe('movement types', function () {
    it('loads the reference catalog with the migration', function () {
        expect(MovementType::query()->count())->toBe(24)
            ->and(MovementType::findByCode(931, MovementDirection::Out)?->name)->toBe('Transferencia, salida')
            ->and(MovementType::findByCode(102, MovementDirection::Out)?->name)->toBe('Anulación de entrada por orden de compra');
    });

    it('pairs every movement with a reversal in the opposite direction that reverses it back', function () {
        foreach (MovementType::query()->get() as $type) {
            $reversal = $type->reversal();

            expect($reversal)->not->toBeNull()
                ->and($reversal?->direction)->not->toBe($type->direction)
                ->and($reversal?->reversal_code)->toBe($type->code);
        }
    });

    it('cannot be changed from the application', function () {
        $type = MovementType::findByCode(101, MovementDirection::In);
        $type?->setAttribute('name', 'Otro nombre');

        $type?->save();
    })->throws(LogicException::class, 'catálogo de referencia');
});

describe('inventory operations', function () {
    it('takes its number from its own range and shows it with ten digits', function () {
        $operation = ledgerOperation($this->warehouse, $this->user, OperationType::Transfer);

        expect($operation->number)->toBeGreaterThanOrEqual(4900000000)->toBeLessThanOrEqual(4999999999)
            ->and($operation->formattedNumber())->toHaveLength(10)->toStartWith('49');
    });

    it('rejects a number outside the range of inventory operations', function () {
        $error = databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::Transfer, ['number' => 4500000001]));

        expect($error->getMessage())->toContain('inventory_operations_number_check');
    });

    it('requires a document reference for document operations and none for the rest', function () {
        expect(databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::Receipt))->getMessage())
            ->toContain('inventory_operations_type_reference_check')
            ->and(databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::Transfer, ['reference_type' => 'goods_receipt', 'reference_id' => 1]))->getMessage())
            ->toContain('inventory_operations_type_reference_check');
    });

    it('requires a reason for adjustments and a note for reversals', function () {
        expect(databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::AdjustmentIn))->getMessage())
            ->toContain('inventory_operations_adjustment_reason_check');

        $original = ledgerOperation($this->warehouse, $this->user, OperationType::Transfer);

        expect(databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::Reversal, ['reverses_operation_id' => $original->id]))->getMessage())
            ->toContain('inventory_operations_reversal_reason_check');
    });
});

describe('append-only ledger (INV-003)', function () {
    beforeEach(function () {
        $this->operation = ledgerOperation($this->warehouse, $this->user, OperationType::AdjustmentIn, ['reason_code' => 'found']);
        $this->movement = ledgerMovement($this->operation, $this->stock, 712, 10);
    });

    it('rejects a direct update or delete of an operation', function (string $statement) {
        $error = databaseErrorOf(fn () => DB::statement($statement, [$this->operation->id]));

        expect($error->getMessage())->toContain('inventory_operations is append only');
    })->with([
        'UPDATE' => "UPDATE inventory_operations SET notes = 'cambio' WHERE id = ?",
        'DELETE' => 'DELETE FROM inventory_operations WHERE id = ?',
    ]);

    it('rejects a direct update or delete of a movement', function (string $statement) {
        $error = databaseErrorOf(fn () => DB::statement($statement, [$this->movement->id]));

        expect($error->getMessage())->toContain('stock_movements is append only');
    })->with([
        'UPDATE' => 'UPDATE stock_movements SET delta = 99 WHERE id = ?',
        'DELETE' => 'DELETE FROM stock_movements WHERE id = ?',
    ]);

    it('rejects a movement code with the wrong sign', function () {
        $operation = ledgerOperation($this->warehouse, $this->user, OperationType::Receipt, ['reference_type' => 'goods_receipt', 'reference_id' => 1]);

        $error = databaseErrorOf(fn () => ledgerMovement($operation, $this->stock, 101, -5));

        expect($error->getMessage())->toContain('stock_movements_movement_type_foreign');
    });

    it('rejects two lines of one operation on the same stock row', function () {
        $error = databaseErrorOf(fn () => ledgerMovement($this->operation, $this->stock, 712, 3));

        expect($error->getMessage())->toContain('stock_movements_operation_id_stock_id_unique');
    });

    it('rejects an item number below one', function () {
        $stock = secondStockRow($this->stock);

        $error = databaseErrorOf(fn () => ledgerMovement($this->operation, $stock, 712, 3, ['item_number' => 0]));

        expect($error->getMessage())->toContain('stock_movements_item_number_check');
    });

    it('rejects two lines of one item in the same direction (E3.04)', function () {
        $stock = secondStockRow($this->stock);

        $error = databaseErrorOf(fn () => ledgerMovement($this->operation, $stock, 712, 3, ['item_number' => $this->movement->item_number]));

        expect($error->getMessage())->toContain('stock_movements_operation_id_item_number_direction_unique');
    });
});

describe('stock rows', function () {
    // Un físico negativo viola también stocks_committed_within_on_hand_check
    // (reservado y bloqueado no bajan de cero), y PostgreSQL informa la primera
    // restricción violada por nombre.
    it('rejects negative quantities and commitments above the quantity on hand', function (array $quantities, string $constraints) {
        $error = databaseErrorOf(fn () => DB::table('stocks')->where('id', $this->stock->id)->update($quantities));

        expect($error->getMessage())->toMatch("/\"(?:{$constraints})\"/");
    })->with([
        'on hand negative' => [['quantity_on_hand' => -1], 'stocks_quantity_on_hand_check|stocks_committed_within_on_hand_check'],
        'reserved negative' => [['quantity_reserved' => -1], 'stocks_quantity_reserved_check'],
        'blocked negative' => [['quantity_blocked' => -1], 'stocks_quantity_blocked_check'],
        'reserved and blocked above on hand' => [['quantity_reserved' => 6, 'quantity_blocked' => 5], 'stocks_committed_within_on_hand_check'],
    ]);

    it('keeps a single row per article, location and lot, also without lot', function () {
        $error = databaseErrorOf(fn () => insertStock([
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'product_id' => $this->product->id,
        ]));

        expect($error->getMessage())->toContain('stocks_product_id_location_id_lot_id_unique');
    });

    it('rejects a location of another warehouse', function () {
        $other = Warehouse::factory()->create();

        $error = databaseErrorOf(fn () => insertStock([
            'warehouse_id' => $other->id,
            'location_id' => $this->location->id,
            'product_id' => Product::factory()->create()->id,
        ]));

        expect($error->getMessage())->toContain('stocks_location_id_warehouse_id_foreign');
    });

    it('computes the free quantity from on hand, reserved and blocked', function () {
        DB::table('stocks')->where('id', $this->stock->id)->update(['quantity_reserved' => 3, 'quantity_blocked' => 2]);

        expect($this->stock->refresh()->quantity_available)->toBe(5);
    });
});

describe('partial reversal by line (ADR-013)', function () {
    beforeEach(function () {
        $this->original = ledgerOperation($this->warehouse, $this->user, OperationType::AdjustmentIn, ['reason_code' => 'found']);
        $this->line = ledgerMovement($this->original, $this->stock, 712, 10);
        $this->reversal = fn () => ledgerOperation($this->warehouse, $this->user, OperationType::Reversal, [
            'reverses_operation_id' => $this->original->id,
            'notes' => 'Conteo equivocado',
        ]);
    });

    it('accepts reversals of one line up to its quantity', function () {
        ledgerMovement(($this->reversal)(), $this->stock, 711, -6, ['reverses_movement_id' => $this->line->id]);
        ledgerMovement(($this->reversal)(), $this->stock, 711, -4, ['reverses_movement_id' => $this->line->id]);

        expect((int) $this->line->reversals()->sum('delta'))->toBe(-10);
    });

    it('translates a reversal above the remaining quantity to REVERSAL_EXCEEDS_REMAINING', function () {
        ledgerMovement(($this->reversal)(), $this->stock, 711, -6, ['reverses_movement_id' => $this->line->id]);
        $second = ($this->reversal)();

        $error = databaseErrorOf(fn () => ledgerMovement($second, $this->stock, 711, -5, ['reverses_movement_id' => $this->line->id]));

        expect(app(DatabaseErrorTranslator::class)->translate($error))->toBeInstanceOf(ReversalExceedsRemaining::class);
    });

    it('rejects a malformed reversal line as a programming error', function (int $code, int $delta) {
        $reversal = ($this->reversal)();

        $error = databaseErrorOf(fn () => ledgerMovement($reversal, $this->stock, $code, $delta, ['reverses_movement_id' => $this->line->id]));

        expect($error->getMessage())->toContain('REVERSAL_LINE_MISMATCH')
            ->and(app(DatabaseErrorTranslator::class)->translate($error))->toBeNull();
    })->with([
        'same sign as the original' => [712, 3],
        'another reversal code' => [552, 3],
    ]);

    it('rejects a reversal line without its original line', function () {
        $reversal = ($this->reversal)();

        $error = databaseErrorOf(fn () => ledgerMovement($reversal, $this->stock, 711, -3));

        expect($error->getMessage())->toContain('REVERSAL_LINE_WITHOUT_ORIGINAL')
            ->and(app(DatabaseErrorTranslator::class)->translate($error))->toBeNull();
    });
});

describe('stock adjustment documents (E3.05)', function () {
    beforeEach(function () {
        $this->adjustment = StockAdjustment::query()->create([
            'number' => StockAdjustment::nextNumber(),
            'warehouse_id' => $this->warehouse->id,
            'direction' => MovementDirection::Out,
            'reason_code' => 'loss',
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]);
        $this->adjustmentItem = fn (array $attributes = []): int => DB::table('stock_adjustment_items')->insertGetId([
            'adjustment_id' => $this->adjustment->id,
            'warehouse_id' => $this->warehouse->id,
            'item_number' => 1,
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity' => 10,
            'approval_threshold' => 5,
            'status' => 'pending',
            ...$attributes,
        ]);
        $this->adjustmentOperation = fn (): InventoryOperation => ledgerOperation($this->warehouse, $this->user, OperationType::AdjustmentOut, [
            'reason_code' => 'loss',
            'reference_type' => 'stock_adjustment',
            'reference_id' => $this->adjustment->id,
        ]);
    });

    it('numbers the documents in their own range', function () {
        $error = databaseErrorOf(fn () => DB::table('stock_adjustments')->insert([
            'number' => 4900000001,
            'warehouse_id' => $this->warehouse->id,
            'direction' => 'out',
            'reason_code' => 'loss',
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]));

        expect($this->adjustment->number)->toBeGreaterThanOrEqual(5100000000)->toBeLessThanOrEqual(5199999999)
            ->and($error->getMessage())->toContain('stock_adjustments_number_check');
    });

    it('admits only the reasons of the direction', function (string $direction, string $reason) {
        $error = databaseErrorOf(fn () => DB::table('stock_adjustments')->insert([
            'number' => StockAdjustment::nextNumber(),
            'warehouse_id' => $this->warehouse->id,
            'direction' => $direction,
            'reason_code' => $reason,
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]));

        expect($error->getMessage())->toContain('stock_adjustments_reason_code_check');
    })->with([['in', 'damage'], ['in', 'loss'], ['out', 'found'], ['in', 'initial_load']]);

    it('keeps the header append only', function () {
        $error = databaseErrorOf(fn () => DB::table('stock_adjustments')->where('id', $this->adjustment->id)->update(['notes' => 'Otra nota']));

        expect($error->getMessage())->toContain('stock_adjustments is append only');
    });

    it('ties the status of an item to its threshold and its operation', function (array $attributes, string $constraint) {
        expect(databaseErrorOf(fn () => ($this->adjustmentItem)($attributes))->getMessage())->toContain($constraint);
    })->with([
        'pending within the threshold' => [['quantity' => 5], 'stock_adjustment_items_threshold_status_check'],
        'pending without threshold' => [['approval_threshold' => null], 'stock_adjustment_items_threshold_status_check'],
        'posted without operation' => [['status' => 'posted', 'approval_threshold' => null], 'stock_adjustment_items_operation_check'],
        'zero threshold' => [['approval_threshold' => 0], 'stock_adjustment_items_approval_threshold_check'],
    ]);

    it('rejects a posted item above its threshold', function () {
        $operation = ($this->adjustmentOperation)();

        expect(databaseErrorOf(fn () => ($this->adjustmentItem)(['status' => 'posted', 'operation_id' => $operation->id]))->getMessage())
            ->toContain('stock_adjustment_items_threshold_status_check');
    });

    it('lets a pending item change once, to approved or rejected, and nothing else', function () {
        $id = ($this->adjustmentItem)();
        $operation = ($this->adjustmentOperation)();
        $decision = ['decided_by' => $this->user->id, 'decided_at' => now()];
        $item = fn () => DB::table('stock_adjustment_items')->where('id', $id);

        expect(databaseErrorOf(fn () => $item()->update(['quantity' => 11, 'status' => 'rejected', 'decision_notes' => 'Conteo dudoso', ...$decision]))->getMessage())
            ->toContain('Only the decision')
            ->and(databaseErrorOf(fn () => $item()->update(['status' => 'rejected', ...$decision]))->getMessage())
            ->toContain('stock_adjustment_items_rejection_notes_check');

        $item()->update(['status' => 'approved', 'operation_id' => $operation->id, ...$decision]);

        expect(databaseErrorOf(fn () => $item()->update(['status' => 'rejected', 'operation_id' => null, 'decision_notes' => 'Tarde']))->getMessage())
            ->toContain('ADJUSTMENT_ITEM_NOT_PENDING')
            ->and(databaseErrorOf(fn () => $item()->delete())->getMessage())
            ->toContain('does not allow DELETE');
    });

    it('lets one document originate several operations, unlike the other references (INV-007)', function () {
        ($this->adjustmentOperation)();
        ($this->adjustmentOperation)();
        ledgerOperation($this->warehouse, $this->user, OperationType::Receipt, ['reference_type' => 'goods_receipt', 'reference_id' => 1]);

        $error = databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::Receipt, ['reference_type' => 'goods_receipt', 'reference_id' => 1]));

        expect($error->getMessage())->toContain('inventory_operations_reference_unique');
    });

    it('admits an adjustment without document and a document reference only on manual adjustments', function () {
        ledgerOperation($this->warehouse, $this->user, OperationType::AdjustmentOut, ['reason_code' => 'damage']);

        $initialLoad = databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::AdjustmentIn, [
            'reason_code' => 'initial_load',
            'reference_type' => 'stock_adjustment',
            'reference_id' => $this->adjustment->id,
        ]));
        $transfer = databaseErrorOf(fn () => ledgerOperation($this->warehouse, $this->user, OperationType::Transfer, [
            'reference_type' => 'stock_adjustment',
            'reference_id' => $this->adjustment->id,
        ]));

        expect($initialLoad->getMessage())->toContain('inventory_operations_type_reference_check')
            ->and($transfer->getMessage())->toContain('inventory_operations_type_reference_check');
    });
});
