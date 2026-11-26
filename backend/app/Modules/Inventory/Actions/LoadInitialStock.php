<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\AdjustmentItem;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Exceptions\LocationBlocked;
use App\Modules\Inventory\Exceptions\LocationCapacityExceeded;
use App\Modules\Inventory\Exceptions\ProductNotOperable;
use App\Modules\Inventory\Exceptions\SystemLocationNotAllowed;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Exceptions\WarehouseInactive;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Carga inicial de stock (E3.06, decisiones del usuario del 26/11/2026, D15
 * del plan E3): una operación adjustment_in con motivo initial_load y hasta
 * MAX_ITEMS posiciones, cada una con un movimiento 561 y su item_number. La
 * operación es el documento: no hay tabla propia porque no hay estados que
 * guardar.
 *
 * No pasa por el umbral de aprobación (REG-024): el control es el permiso
 * inventory.initial_load, exclusivo del Administrador y del Gerente de
 * operaciones, y la auditoría del motor. Se admite en cualquier momento, no
 * solo en la puesta en marcha del depósito: cubre un sector nuevo y la
 * corrección de una carga mal hecha (revertir con 562 y volver a cargar).
 *
 * Regla propia, antes de contabilizar: solo en ubicaciones de almacenamiento,
 * nunca en una de sistema (REG-014). StockLedger valida el resto bajo
 * bloqueo: depósito activo, artículo operable, ubicación activa y capacidad.
 * La operación es atómica: si falla una posición, no se carga ninguna.
 */
final class LoadInitialStock
{
    /** Posiciones que admite una carga inicial. */
    public const MAX_ITEMS = 100;

    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  list<AdjustmentItem>  $items
     *
     * @throws SystemLocationNotAllowed
     * @throws WarehouseInactive
     * @throws ProductNotOperable
     * @throws LocationBlocked
     * @throws LocationCapacityExceeded
     */
    public function __invoke(int $warehouseId, array $items, int $performedBy, ?string $notes = null, ?string $idempotencyKey = null): InventoryOperation
    {
        if ($items === [] || count($items) > self::MAX_ITEMS) {
            throw new LogicException('Una carga inicial tiene entre 1 y '.self::MAX_ITEMS.' posiciones.');
        }

        return DB::transaction(function () use ($warehouseId, $items, $performedBy, $notes, $idempotencyKey): InventoryOperation {
            $locations = self::locations($warehouseId, $items);
            $code = OperationType::AdjustmentIn->movementCodes(AdjustmentReason::InitialLoad)[0] ?? throw new LogicException('Sin código de movimiento para la carga inicial.');
            $lines = [];

            foreach ($items as $index => $item) {
                $number = $index + 1;
                $location = $locations->get($item->locationId) ?? throw (new ModelNotFoundException)->setModel(Location::class, [$item->locationId]);

                if ($location->is_system) {
                    throw new SystemLocationNotAllowed($number, $location);
                }

                $lines[] = new MovementLine($item->productId, $item->locationId, $code, $item->quantity, itemNumber: $number);
            }

            return $this->ledger->apply(new OperationDraft(
                type: OperationType::AdjustmentIn,
                warehouseId: $warehouseId,
                performedBy: $performedBy,
                lines: $lines,
                reasonCode: AdjustmentReason::InitialLoad,
                notes: $notes,
                idempotencyKey: $idempotencyKey,
            ));
        }, attempts: 3);
    }

    /**
     * Ubicaciones del depósito, para la regla de ubicación de sistema.
     *
     * @param  list<AdjustmentItem>  $items
     * @return Collection<int, Location>
     */
    private static function locations(int $warehouseId, array $items): Collection
    {
        $ids = array_values(array_unique(array_map(fn (AdjustmentItem $item): int => $item->locationId, $items)));

        return Location::query()->where('warehouse_id', $warehouseId)->whereKey($ids)->get()->keyBy('id');
    }
}
