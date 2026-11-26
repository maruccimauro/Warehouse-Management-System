<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Data\TransferItem;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Exceptions\LocationBlocked;
use App\Modules\Inventory\Exceptions\LocationCapacityExceeded;
use App\Modules\Inventory\Exceptions\ProductNotOperable;
use App\Modules\Inventory\Exceptions\ReservedStockNotAvailable;
use App\Modules\Inventory\Exceptions\SameSourceAndDestination;
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
 * Transferencia de stock entre ubicaciones del mismo depósito (CU-03, E3.04),
 * como el traspaso 311 de un documento de material: una operación transfer
 * con una o más posiciones, y cada posición son dos líneas 931 (salida del
 * origen y entrada en el destino) con el mismo item_number (decisión del
 * usuario del 26/11/2026).
 *
 * Reglas propias, por posición y antes de contabilizar:
 * - Origen distinto del destino: 422 SAME_SOURCE_AND_DESTINATION (INV-004).
 * - Destino que no sea de sistema: 422 SYSTEM_LOCATION_NOT_ALLOWED (REG-014,
 *   DM-14). El origen sí puede serlo: se transfiere lo libre de RECEPCION.
 *   system_role se fija al crear la ubicación y no cambia, así que se lee
 *   sin bloqueo.
 *
 * StockLedger valida el resto bajo bloqueo: depósito activo, artículo
 * operable, ubicaciones activas, capacidad del destino y stock libre del
 * origen. La operación es atómica: si falla una posición, no se contabiliza
 * ninguna.
 */
final class TransferStock
{
    /** Posiciones que admite una transferencia. */
    public const MAX_ITEMS = 100;

    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  list<TransferItem>  $items
     *
     * @throws SameSourceAndDestination
     * @throws SystemLocationNotAllowed
     * @throws WarehouseInactive
     * @throws ProductNotOperable
     * @throws LocationBlocked
     * @throws LocationCapacityExceeded
     * @throws InsufficientStock
     * @throws ReservedStockNotAvailable
     */
    public function __invoke(int $warehouseId, array $items, int $performedBy, ?string $notes = null, ?string $idempotencyKey = null): InventoryOperation
    {
        if ($items === [] || count($items) > self::MAX_ITEMS) {
            throw new LogicException('Una transferencia tiene entre 1 y '.self::MAX_ITEMS.' posiciones.');
        }

        return DB::transaction(function () use ($warehouseId, $items, $performedBy, $notes, $idempotencyKey): InventoryOperation {
            $locations = self::locations($warehouseId, $items);
            $lines = [];

            foreach ($items as $index => $item) {
                $number = $index + 1;
                $destination = $locations->get($item->toLocationId) ?? throw (new ModelNotFoundException)->setModel(Location::class, [$item->toLocationId]);

                if ($item->fromLocationId === $item->toLocationId) {
                    throw new SameSourceAndDestination($number, $destination);
                }

                if ($destination->is_system) {
                    throw new SystemLocationNotAllowed($number, $destination);
                }

                $lines[] = new MovementLine($item->productId, $item->fromLocationId, 931, -$item->quantity, itemNumber: $number);
                $lines[] = new MovementLine($item->productId, $item->toLocationId, 931, $item->quantity, itemNumber: $number);
            }

            return $this->ledger->apply(new OperationDraft(
                type: OperationType::Transfer,
                warehouseId: $warehouseId,
                performedBy: $performedBy,
                lines: $lines,
                notes: $notes,
                idempotencyKey: $idempotencyKey,
            ));
        }, attempts: 3);
    }

    /**
     * Ubicaciones de destino del depósito, para las reglas de la posición.
     *
     * @param  list<TransferItem>  $items
     * @return Collection<int, Location>
     */
    private static function locations(int $warehouseId, array $items): Collection
    {
        $ids = array_values(array_unique(array_map(fn (TransferItem $item): int => $item->toLocationId, $items)));

        return Location::query()->where('warehouse_id', $warehouseId)->whereKey($ids)->get()->keyBy('id');
    }
}
