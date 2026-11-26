<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Inventory\Exceptions\AdjustmentItemNotPending;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Services\AdjustmentItemDecision;
use App\Shared\Exceptions\SelfApprovalNotAllowed;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Rechazo de posiciones de ajuste pendientes (E3.23, REG-024, D13 del plan de
 * E3): un motivo por acción, copiado en decision_notes de cada posición. No
 * mueve stock ni crea operación, y por eso no exige que el depósito esté
 * activo: rechazar lo pendiente de un depósito que se va a desactivar es
 * justamente lo que corresponde.
 *
 * Bloquea la cabecera (nivel 1 de ADR-003) y las posiciones por id (nivel 2),
 * como la aprobación, para que las dos se serialicen sobre el mismo documento.
 * Todo o nada: una posición ya decidida responde 409.
 */
final class RejectAdjustmentItems
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $itemNumbers
     *
     * @throws SelfApprovalNotAllowed
     * @throws AdjustmentItemNotPending
     */
    public function __invoke(StockAdjustment $adjustment, array $itemNumbers, int $rejectedBy, string $notes): StockAdjustment
    {
        $notes = trim($notes);

        if ($notes === '') {
            throw new LogicException('El rechazo de posiciones de ajuste exige motivo (REG-024).');
        }

        AdjustmentItemDecision::ensureNotCreator($adjustment, $rejectedBy);

        return DB::transaction(function () use ($adjustment, $itemNumbers, $rejectedBy, $notes): StockAdjustment {
            $items = AdjustmentItemDecision::lockPending($adjustment, $itemNumbers);

            AdjustmentItemDecision::record($items, StockAdjustmentItemStatus::Rejected, $rejectedBy, $notes);

            $numbers = $items->pluck('item_number')->all();

            $this->audit->record('stock_adjustment.items_rejected', $adjustment, $adjustment->warehouse_id,
                ['items' => $numbers, 'status' => StockAdjustmentItemStatus::Pending->value],
                ['items' => $numbers, 'status' => StockAdjustmentItemStatus::Rejected->value, 'decision_notes' => $notes],
            );

            return $adjustment;
        }, attempts: 3);
    }
}
