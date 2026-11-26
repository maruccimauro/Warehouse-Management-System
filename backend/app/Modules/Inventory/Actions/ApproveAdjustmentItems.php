<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Inventory\Exceptions\AdjustmentItemNotPending;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Exceptions\LocationBlocked;
use App\Modules\Inventory\Exceptions\LocationCapacityExceeded;
use App\Modules\Inventory\Exceptions\ProductNotOperable;
use App\Modules\Inventory\Exceptions\ReservedStockNotAvailable;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentItem;
use App\Modules\Inventory\Services\AdjustmentItemDecision;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Actions\LockActiveWarehouse;
use App\Modules\Warehouse\Exceptions\WarehouseInactive;
use App\Shared\Exceptions\SelfApprovalNotAllowed;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Aprobación de posiciones de ajuste pendientes (E3.23, REG-024, D13 del plan
 * de E3). Las posiciones aprobadas en una acción se contabilizan juntas en una
 * operación nueva del documento (reference_type stock_adjustment), con el
 * item_number de cada posición, el código de movimiento del motivo y el
 * aprobador como responsable.
 *
 * Bloqueos en el orden de ADR-003: depósito FOR SHARE (0), cabecera FOR
 * UPDATE (1), posiciones FOR UPDATE por id (2) y después StockLedger. Todo o
 * nada: una posición ya decidida responde 409 y una sin stock 422 con su
 * número en context.item, y todas siguen pendientes. No se recompara el
 * umbral: la posición ya esperó la aprobación que pedía. StockLedger revalida
 * todo lo demás (depósito activo, estado del artículo, ubicación,
 * disponibilidad y capacidad) con el stock de este momento.
 */
final class ApproveAdjustmentItems
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly LockActiveWarehouse $lockActiveWarehouse,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>  $itemNumbers
     *
     * @throws SelfApprovalNotAllowed
     * @throws WarehouseInactive
     * @throws AdjustmentItemNotPending
     * @throws ProductNotOperable
     * @throws LocationBlocked
     * @throws LocationCapacityExceeded
     * @throws InsufficientStock
     * @throws ReservedStockNotAvailable
     */
    public function __invoke(
        StockAdjustment $adjustment,
        array $itemNumbers,
        int $approvedBy,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): StockAdjustment {
        AdjustmentItemDecision::ensureNotCreator($adjustment, $approvedBy);

        return DB::transaction(function () use ($adjustment, $itemNumbers, $approvedBy, $notes, $idempotencyKey): StockAdjustment {
            ($this->lockActiveWarehouse)($adjustment->warehouse_id);

            $items = AdjustmentItemDecision::lockPending($adjustment, $itemNumbers);

            $type = $adjustment->operationType();
            $code = $type->movementCodes($adjustment->reason_code)[0] ?? throw new LogicException("Sin código de movimiento para {$adjustment->reason_code->value}.");
            $sign = $adjustment->direction === MovementDirection::In ? 1 : -1;

            $operation = $this->ledger->apply(new OperationDraft(
                type: $type,
                warehouseId: $adjustment->warehouse_id,
                performedBy: $approvedBy,
                lines: array_values($items->map(fn (StockAdjustmentItem $item): MovementLine => new MovementLine(
                    $item->product_id,
                    $item->location_id,
                    $code,
                    $sign * $item->quantity,
                    itemNumber: $item->item_number,
                ))->all()),
                reasonCode: $adjustment->reason_code,
                referenceType: StockAdjustment::REFERENCE_TYPE,
                referenceId: $adjustment->id,
                notes: $notes,
                idempotencyKey: $idempotencyKey,
            ));

            AdjustmentItemDecision::record($items, StockAdjustmentItemStatus::Approved, $approvedBy, $notes, $operation->id);

            $numbers = $items->pluck('item_number')->all();

            $this->audit->record('stock_adjustment.items_approved', $adjustment, $adjustment->warehouse_id,
                ['items' => $numbers, 'status' => StockAdjustmentItemStatus::Pending->value],
                ['items' => $numbers, 'status' => StockAdjustmentItemStatus::Approved->value, 'operation_id' => $operation->id, 'decision_notes' => $notes],
                $operation->id,
            );

            return $adjustment;
        }, attempts: 3);
    }
}
