<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Models\ProductWarehouseSetting;
use App\Modules\Inventory\Data\AdjustmentItem;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Exceptions\LocationBlocked;
use App\Modules\Inventory\Exceptions\LocationCapacityExceeded;
use App\Modules\Inventory\Exceptions\ReservedStockNotAvailable;
use App\Modules\Inventory\Exceptions\SystemLocationNotAllowed;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Actions\LockActiveWarehouse;
use App\Modules\Warehouse\Exceptions\WarehouseInactive;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Ajuste de stock con motivo (CU-04, E3.05, decisiones del usuario del
 * 26/11/2026): un documento con una dirección, un único motivo de la lista
 * cerrada (INV-008) y hasta MAX_ITEMS posiciones.
 *
 * Cada posición se compara con el umbral de su artículo en el depósito para
 * la dirección del documento (product_warehouse_settings). Sin umbral, o sin
 * superarlo, nace posted; si lo supera, nace pending y no mueve stock hasta
 * que otro usuario la aprueba (E3.23). Las posted se contabilizan juntas en
 * una operación adjustment_in o adjustment_out que referencia al documento,
 * con el mismo item_number de la posición. Si ninguna se contabiliza, el
 * documento queda sin operación.
 *
 * El umbral se lee sin bloqueo: es un dato maestro, y leer el valor de un
 * instante antes equivale a que el ajuste haya llegado un instante antes.
 *
 * Reglas propias, antes de contabilizar: un ajuste positivo no tiene como
 * destino una ubicación de sistema (REG-014); el negativo sí la admite.
 * StockLedger valida el resto bajo bloqueo para las posted. El depósito se
 * bloquea primero (nivel 0 de ADR-003) aunque ninguna posición se
 * contabilice, para que un depósito inactivo no acumule solicitudes.
 */
final class AdjustStock
{
    /** Posiciones que admite un ajuste. */
    public const MAX_ITEMS = 100;

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly LockActiveWarehouse $lockActiveWarehouse,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Motivos que admite cada dirección; initial_load tiene su propia
     * operación (E3.06).
     *
     * @return list<AdjustmentReason>
     */
    public static function reasonsFor(MovementDirection $direction): array
    {
        return $direction === MovementDirection::In
            ? [AdjustmentReason::Found, AdjustmentReason::Correction]
            : [AdjustmentReason::Damage, AdjustmentReason::Loss, AdjustmentReason::Correction];
    }

    /**
     * @param  list<AdjustmentItem>  $items
     *
     * @throws WarehouseInactive
     * @throws SystemLocationNotAllowed
     * @throws LocationBlocked
     * @throws LocationCapacityExceeded
     * @throws InsufficientStock
     * @throws ReservedStockNotAvailable
     */
    public function __invoke(
        int $warehouseId,
        MovementDirection $direction,
        AdjustmentReason $reason,
        array $items,
        int $performedBy,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): StockAdjustment {
        if ($items === [] || count($items) > self::MAX_ITEMS) {
            throw new LogicException('Un ajuste tiene entre 1 y '.self::MAX_ITEMS.' posiciones.');
        }

        if (! in_array($reason, self::reasonsFor($direction), true)) {
            throw new LogicException("El motivo {$reason->value} no corresponde a un ajuste de dirección {$direction->value}.");
        }

        return DB::transaction(function () use ($warehouseId, $direction, $reason, $items, $performedBy, $notes, $idempotencyKey): StockAdjustment {
            ($this->lockActiveWarehouse)($warehouseId);

            $locations = self::locations($warehouseId, $items);
            $thresholds = self::thresholds($warehouseId, $direction, $items);

            $adjustment = StockAdjustment::query()->create([
                'number' => StockAdjustment::nextNumber(),
                'warehouse_id' => $warehouseId,
                'direction' => $direction,
                'reason_code' => $reason,
                'notes' => $notes,
                'created_by' => $performedBy,
                'created_at' => Carbon::now(),
            ]);

            $code = $adjustment->operationType()->movementCodes($reason)[0] ?? throw new LogicException("Sin código de movimiento para {$reason->value}.");
            $sign = $direction === MovementDirection::In ? 1 : -1;
            $rows = [];
            $lines = [];

            foreach ($items as $index => $item) {
                $number = $index + 1;
                $location = $locations->get($item->locationId) ?? throw (new ModelNotFoundException)->setModel(Location::class, [$item->locationId]);

                if ($direction === MovementDirection::In && $location->is_system) {
                    throw new SystemLocationNotAllowed($number, $location);
                }

                $threshold = $thresholds[$item->productId] ?? null;
                $pending = $threshold !== null && $item->quantity > $threshold;

                $rows[$number] = [
                    'item_number' => $number,
                    'product_id' => $item->productId,
                    'location_id' => $item->locationId,
                    'quantity' => $item->quantity,
                    'approval_threshold' => $threshold,
                    'status' => $pending ? StockAdjustmentItemStatus::Pending : StockAdjustmentItemStatus::Posted,
                ];

                if (! $pending) {
                    $lines[] = new MovementLine($item->productId, $item->locationId, $code, $sign * $item->quantity, itemNumber: $number);
                }
            }

            $operation = $lines === [] ? null : $this->ledger->apply(new OperationDraft(
                type: $adjustment->operationType(),
                warehouseId: $warehouseId,
                performedBy: $performedBy,
                lines: $lines,
                reasonCode: $reason,
                referenceType: StockAdjustment::REFERENCE_TYPE,
                referenceId: $adjustment->id,
                notes: $notes,
                idempotencyKey: $idempotencyKey,
            ));

            foreach ($rows as $row) {
                $adjustment->items()->create([
                    ...$row,
                    'warehouse_id' => $warehouseId,
                    'operation_id' => $row['status'] === StockAdjustmentItemStatus::Posted ? $operation?->id : null,
                ]);
            }

            $pendingCount = count(array_filter($rows, fn (array $row): bool => $row['status'] === StockAdjustmentItemStatus::Pending));

            $this->audit->record('stock_adjustment.created', $adjustment, $warehouseId, null, [
                'number' => $adjustment->number,
                'direction' => $direction->value,
                'reason_code' => $reason->value,
                'items' => count($rows),
                'pending_items' => $pendingCount,
                'operation_id' => $operation?->id,
            ], $operation?->id);

            return $adjustment;
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

    /**
     * Umbral de cada artículo en el depósito para la dirección del ajuste; un
     * artículo sin fila o con el umbral vacío no lo tiene.
     *
     * @param  list<AdjustmentItem>  $items
     * @return array<int, int|null>
     */
    private static function thresholds(int $warehouseId, MovementDirection $direction, array $items): array
    {
        $column = $direction === MovementDirection::In ? 'adjustment_in_approval_threshold' : 'adjustment_out_approval_threshold';
        $ids = array_values(array_unique(array_map(fn (AdjustmentItem $item): int => $item->productId, $items)));

        /** @var array<int, int|null> $thresholds */
        $thresholds = ProductWarehouseSetting::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $ids)
            ->pluck($column, 'product_id')
            ->map(fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null)
            ->all();

        return $thresholds;
    }
}
