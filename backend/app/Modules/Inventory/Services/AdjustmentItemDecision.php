<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Inventory\Exceptions\AdjustmentItemNotPending;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentItem;
use App\Shared\Exceptions\SelfApprovalNotAllowed;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Lo que comparten la aprobación y el rechazo de posiciones de ajuste (E3.23,
 * D13 del plan de E3): quién decide y qué posiciones se pueden decidir.
 */
final class AdjustmentItemDecision
{
    /** Posiciones que admite una decisión: las de un documento. */
    public const MAX_ITEMS = 100;

    /**
     * El creador no decide ninguna posición de su documento, aunque tenga el
     * permiso de aprobar (REG-024). created_by no cambia: se verifica sin
     * bloqueo.
     *
     * @throws SelfApprovalNotAllowed
     */
    public static function ensureNotCreator(StockAdjustment $adjustment, int $userId): void
    {
        if ($adjustment->created_by === $userId) {
            throw new SelfApprovalNotAllowed;
        }
    }

    /**
     * Bloquea la cabecera con FOR UPDATE (nivel 1 de ADR-003) y las posiciones
     * pedidas con FOR UPDATE por id (nivel 2), y verifica bajo bloqueo que
     * todas sigan pending: dos decisiones simultáneas sobre el mismo documento
     * se serializan en la cabecera, y la segunda ve lo que decidió la primera.
     * Todo o nada: una sola posición ya decidida rechaza la acción.
     *
     * @param  list<int>  $itemNumbers  números de posición ya validados contra el documento
     * @return Collection<int, StockAdjustmentItem> en orden de posición
     *
     * @throws AdjustmentItemNotPending
     */
    public static function lockPending(StockAdjustment $adjustment, array $itemNumbers): Collection
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Las posiciones de ajuste se bloquean dentro de la transacción de la Action.');
        }

        if ($itemNumbers === [] || count($itemNumbers) > self::MAX_ITEMS || count(array_unique($itemNumbers)) !== count($itemNumbers)) {
            throw new LogicException('Una decisión indica entre 1 y '.self::MAX_ITEMS.' posiciones distintas.');
        }

        StockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

        $items = StockAdjustmentItem::query()
            ->where('adjustment_id', $adjustment->id)
            ->whereIn('item_number', $itemNumbers)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($items->count() !== count($itemNumbers)) {
            throw new LogicException("Alguna de las posiciones pedidas no es del ajuste {$adjustment->id}.");
        }

        $items = $items->sortBy('item_number')->values();

        foreach ($items as $item) {
            if ($item->status !== StockAdjustmentItemStatus::Pending) {
                throw new AdjustmentItemNotPending($item->item_number, $item->status->value);
            }
        }

        return $items;
    }

    /**
     * Registra la decisión en las posiciones; el trigger
     * stock_adjustment_items_validate_transition la vuelve a validar.
     *
     * @param  Collection<int, StockAdjustmentItem>  $items
     */
    public static function record(Collection $items, StockAdjustmentItemStatus $status, int $decidedBy, ?string $notes, ?int $operationId = null): void
    {
        StockAdjustmentItem::query()->whereKey($items->modelKeys())->update([
            'status' => $status->value,
            'operation_id' => $operationId,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ]);
    }
}
