<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Exceptions\LocationBlocked;
use App\Modules\Inventory\Exceptions\LocationCapacityExceeded;
use App\Modules\Inventory\Exceptions\ProductNotOperable;
use App\Modules\Inventory\Exceptions\ReservedStockNotAvailable;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Warehouse\Actions\LockActiveWarehouse;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Motor del libro de inventario (ADR-002): la única clase que escribe en
 * stocks y stock_movements. Las Actions de negocio arman un OperationDraft y
 * lo contabilizan con apply, dentro de su propia transacción
 * (DB::transaction(..., attempts: 3), ADR-004).
 *
 * Bloquea en el orden global de ADR-003: depósito (nivel 0), artículos con
 * FOR SHARE (REG-017, fuera de los niveles), ubicaciones por id (nivel 3),
 * existencias con FOR UPDATE en una sola consulta por id (nivel 4), reservas
 * consumidas por id (después de sus existencias) y, en una reversión, las
 * líneas originales en orden ascendente, que bloquea el trigger (nivel 5).
 * Toda validación de cantidades ocurre después de obtener los bloqueos.
 *
 * Un borrador mal armado (sin líneas, delta cero, código que no corresponde
 * al tipo) es un error de programación de la Action: LogicException, 500.
 */
final class StockLedger
{
    /** Mayor posición que admite stock_movements.item_number (SMALLINT). */
    public const MAX_ITEM_NUMBER = 32767;

    /** Profundidad de contabilización: habilita la escritura de Stock y StockMovement. */
    private static int $applying = 0;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LockActiveWarehouse $lockActiveWarehouse,
    ) {}

    /**
     * Si hay una contabilización en curso; lo consulta WrittenOnlyByStockLedger.
     */
    public static function isApplying(): bool
    {
        return self::$applying > 0;
    }

    public function apply(OperationDraft $draft): InventoryOperation
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('StockLedger::apply se invoca dentro de la transacción de la Action.');
        }

        self::validateDraft($draft);

        ($this->lockActiveWarehouse)($draft->warehouseId);
        $products = $this->lockProducts($draft);
        $locations = $this->lockLocations($draft);
        $stocks = $this->lockStocks($draft);
        $reservations = $this->lockReservations($draft, $stocks);

        $this->checkAvailability($draft, $stocks, $products, $locations);

        self::$applying++;

        try {
            $operation = $this->record($draft, $stocks, $reservations);
        } finally {
            self::$applying--;
        }

        $this->audit->record('inventory_operation.created', $operation, $operation->warehouse_id, null, [
            'number' => $operation->number,
            'type' => $operation->type->value,
            'reason_code' => $operation->reason_code?->value,
            'reference_type' => $operation->reference_type,
            'reference_id' => $operation->reference_id,
            'reverses_operation_id' => $operation->reverses_operation_id,
            'lines' => count($draft->lines),
        ], $operation->id);

        return $operation;
    }

    /**
     * Paso 1: coherencia del borrador con el tipo de operación (DM-09).
     */
    private static function validateDraft(OperationDraft $draft): void
    {
        if ($draft->lines === []) {
            throw new LogicException('Una operación de inventario tiene al menos una línea.');
        }

        $isReversal = $draft->type === OperationType::Reversal;
        $isAdjustment = in_array($draft->type, [OperationType::AdjustmentIn, OperationType::AdjustmentOut], true);

        if ($isReversal !== ($draft->reversesOperationId !== null)) {
            throw new LogicException('Solo una reversión indica la operación que revierte, y siempre la indica.');
        }

        if ($isAdjustment !== ($draft->reasonCode !== null)) {
            throw new LogicException('Solo un ajuste lleva motivo, y siempre lo lleva (INV-008).');
        }

        $allowedCodes = $draft->type->movementCodes($draft->reasonCode);
        $catalog = MovementType::query()->get()->map(fn (MovementType $type): string => $type->code.':'.$type->direction->value)->all();
        $stockKeys = [];
        $reservationIds = [];

        foreach ($draft->lines as $line) {
            if ($line->delta === 0) {
                throw new LogicException('Una línea mueve una cantidad distinta de cero.');
            }

            $direction = $line->delta > 0 ? MovementDirection::In : MovementDirection::Out;

            if (! in_array($line->movementCode.':'.$direction->value, $catalog, true)
                || ($allowedCodes !== null && ! in_array($line->movementCode, $allowedCodes, true))) {
                throw new LogicException("El código {$line->movementCode} con dirección {$direction->value} no corresponde a una operación {$draft->type->value}.");
            }

            if ($isReversal !== ($line->reversesMovementId !== null)) {
                throw new LogicException('Cada línea de una reversión indica la línea original, y solo ellas.');
            }

            if ($line->reservationId !== null) {
                if ($line->delta > 0 || in_array($line->reservationId, $reservationIds, true)) {
                    throw new LogicException('Una reserva la consume un único retiro de la operación.');
                }

                $reservationIds[] = $line->reservationId;
            }

            $key = self::stockKey($line->productId, $line->locationId);

            if (isset($stockKeys[$key])) {
                throw new LogicException('Una operación mueve cada existencia en una sola línea (INV-004).');
            }

            $stockKeys[$key] = true;
        }

        self::validateItemNumbers($draft);
    }

    /**
     * Posiciones de la operación (stock_movements.item_number): o las indica
     * cada línea o ninguna, y entonces se numeran en el orden del borrador.
     * Una transferencia siempre las indica, y cada posición tiene exactamente
     * una salida y una entrada del mismo artículo por la misma cantidad.
     */
    private static function validateItemNumbers(OperationDraft $draft): void
    {
        $numbered = array_filter($draft->lines, fn (MovementLine $line): bool => $line->itemNumber !== null);
        $isTransfer = $draft->type === OperationType::Transfer;

        if ($numbered === [] && ! $isTransfer) {
            return;
        }

        if (count($numbered) !== count($draft->lines)) {
            throw new LogicException('Las líneas indican todas su posición, o ninguna (una transferencia siempre la indica).');
        }

        /** @var array<int, array{in?: MovementLine, out?: MovementLine}> $items */
        $items = [];

        foreach ($draft->lines as $line) {
            $item = (int) $line->itemNumber;
            $direction = $line->delta > 0 ? 'in' : 'out';

            if ($item < 1 || $item > self::MAX_ITEM_NUMBER || isset($items[$item][$direction])) {
                throw new LogicException("La posición {$item} no es válida o repite una línea de la misma dirección.");
            }

            $items[$item][$direction] = $line;
        }

        if (! $isTransfer) {
            return;
        }

        foreach ($items as $item => $pair) {
            $out = $pair['out'] ?? null;
            $in = $pair['in'] ?? null;

            if ($out === null || $in === null || $out->productId !== $in->productId || $out->delta !== -$in->delta) {
                throw new LogicException("La posición {$item} de la transferencia no tiene una salida y una entrada del mismo artículo por la misma cantidad.");
            }
        }
    }

    /**
     * Paso 3: artículos con FOR SHARE y su estado (REG-017).
     *
     * @return Collection<int, Product>
     */
    private function lockProducts(OperationDraft $draft): Collection
    {
        $ids = array_values(array_unique(array_map(fn (MovementLine $line): int => $line->productId, $draft->lines)));
        sort($ids);

        $products = Product::query()->whereKey($ids)->orderBy('id')->sharedLock()->get()->keyBy('id');

        if ($products->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(Product::class, array_values(array_diff($ids, $products->keys()->all())));
        }

        foreach ($products as $product) {
            if ($product->status === ProductStatus::Blocked && ! $draft->type->admitsBlockedProduct()) {
                throw new ProductNotOperable($product, $draft->type);
            }
        }

        return $products;
    }

    /**
     * Paso 4: ubicaciones por id (nivel 3). FOR SHARE para mover; FOR NO KEY
     * UPDATE para la que recibe unidades y tiene max_units, de modo que dos
     * entradas simultáneas validen la capacidad una después de la otra (D4).
     * El modo se elige con una lectura previa sin bloqueo; si max_units
     * apareció entre esa lectura y el bloqueo, se toma el modo excluyente
     * antes de validar.
     *
     * @return Collection<int, Location>
     */
    private function lockLocations(OperationDraft $draft): Collection
    {
        $incoming = [];

        foreach ($draft->lines as $line) {
            $incoming[$line->locationId] = ($incoming[$line->locationId] ?? 0) + $line->delta;
        }

        $ids = array_keys($incoming);
        sort($ids);

        $preview = self::locationsOf($draft->warehouseId, $ids)->get()->keyBy('id');

        if ($preview->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(Location::class, array_values(array_diff($ids, $preview->keys()->all())));
        }

        $locations = new Collection;

        foreach ($ids as $id) {
            $checksCapacity = $draft->type->checksCapacity() && $incoming[$id] > 0;
            $exclusive = $checksCapacity && $preview->get($id)?->max_units !== null;
            $location = self::lockLocation($draft->warehouseId, $id, $exclusive);

            if ($checksCapacity && ! $exclusive && $location->max_units !== null) {
                $location = self::lockLocation($draft->warehouseId, $id, true);
            }

            if ($location->status !== LocationStatus::Active) {
                throw new LocationBlocked($location);
            }

            if ($checksCapacity && $location->max_units !== null) {
                $current = (int) Stock::query()->where('location_id', $id)->sum('quantity_on_hand');

                if ($current + $incoming[$id] > $location->max_units) {
                    throw new LocationCapacityExceeded($location, $current, $incoming[$id]);
                }
            }

            $locations->put($id, $location);
        }

        return $locations;
    }

    /**
     * @param  list<int>  $ids
     * @return Builder<Location>
     */
    private static function locationsOf(int $warehouseId, array $ids): Builder
    {
        return Location::query()->where('warehouse_id', $warehouseId)->whereKey($ids);
    }

    private static function lockLocation(int $warehouseId, int $id, bool $exclusive): Location
    {
        return self::locationsOf($warehouseId, [$id])->lock($exclusive ? 'for no key update' : 'for share')->firstOrFail();
    }

    /**
     * Pasos 5 y 6: crea las existencias que faltan y bloquea todas las de la
     * operación con FOR UPDATE, por id, en una sola consulta (nivel 4). El
     * INSERT nombra la restricción única en lugar de inferirla de lot_id nulo;
     * dos altas simultáneas de la misma existencia se esperan y una no hace
     * nada.
     *
     * @return Collection<string, Stock>
     */
    private function lockStocks(OperationDraft $draft): Collection
    {
        $pairs = array_map(fn (MovementLine $line): array => [$line->productId, $line->locationId], $draft->lines);
        sort($pairs);

        $bindings = [];

        foreach ($pairs as [$productId, $locationId]) {
            array_push($bindings, $draft->warehouseId, $locationId, $productId);
        }

        $values = implode(', ', array_fill(0, count($pairs), '(?, ?, ?)'));

        DB::insert(
            "INSERT INTO stocks (warehouse_id, location_id, product_id) VALUES {$values} ON CONFLICT ON CONSTRAINT stocks_product_id_location_id_lot_id_unique DO NOTHING",
            $bindings,
        );

        return Stock::query()
            ->where('warehouse_id', $draft->warehouseId)
            ->whereNull('lot_id')
            ->where(function (Builder $query) use ($pairs): void {
                foreach ($pairs as [$productId, $locationId]) {
                    $query->orWhere(fn (Builder $pair) => $pair->where('product_id', $productId)->where('location_id', $locationId));
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Stock $stock): string => self::stockKey($stock->product_id, $stock->location_id));
    }

    /**
     * Reservas que consumen los retiros, con FOR UPDATE por id, después de sus
     * existencias. Una reserva que no está activa, que es de otra existencia o
     * que no cubre el retiro es un error de la Action que la eligió.
     *
     * @param  Collection<string, Stock>  $stocks
     * @return Collection<int, StockReservation>
     */
    private function lockReservations(OperationDraft $draft, Collection $stocks): Collection
    {
        $lines = array_filter($draft->lines, fn (MovementLine $line): bool => $line->reservationId !== null);

        if ($lines === []) {
            return new Collection;
        }

        $ids = array_map(fn (MovementLine $line): int => (int) $line->reservationId, $lines);
        sort($ids);

        $reservations = StockReservation::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($lines as $line) {
            $reservation = $reservations->get((int) $line->reservationId);
            $stock = $stocks->get(self::stockKey($line->productId, $line->locationId));

            if ($reservation === null
                || $reservation->status !== ReservationStatus::Active
                || $reservation->stock_id !== $stock?->id
                || -$line->delta > $reservation->quantity) {
                throw new LogicException("La reserva {$line->reservationId} no está activa, es de otra existencia o no cubre el retiro.");
            }
        }

        return $reservations;
    }

    /**
     * Paso 7: un retiro sin reserva no toma lo reservado ni lo bloqueado
     * (INV-001, INV-005). Si el físico sin bloquear alcanza, lo que falta está
     * reservado: RESERVED_STOCK_NOT_AVAILABLE; si no, INSUFFICIENT_STOCK. Un
     * retiro con reserva la consume, y la reserva ya está dentro del físico
     * por stocks_committed_within_on_hand_check.
     *
     * @param  Collection<string, Stock>  $stocks
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Location>  $locations
     */
    private function checkAvailability(OperationDraft $draft, Collection $stocks, Collection $products, Collection $locations): void
    {
        foreach ($draft->lines as $line) {
            if ($line->delta > 0 || $line->reservationId !== null) {
                continue;
            }

            $stock = self::stockOf($stocks, $line);
            $requested = -$line->delta;

            if ($requested <= $stock->quantity_available) {
                continue;
            }

            /** @var Product $product */
            $product = $products->get($line->productId);
            /** @var Location $location */
            $location = $locations->get($line->locationId);

            throw $requested <= $stock->quantity_on_hand - $stock->quantity_blocked
                ? new ReservedStockNotAvailable($stock, $product, $location, $requested)
                : new InsufficientStock($stock, $product, $location, $requested);
        }
    }

    /**
     * Paso 8: cabecera, saldos y líneas con balance_after. En una reversión,
     * las líneas se insertan en orden ascendente de la línea original, porque
     * el trigger la bloquea al insertar (nivel 5).
     *
     * @param  Collection<string, Stock>  $stocks
     * @param  Collection<int, StockReservation>  $reservations
     */
    private function record(OperationDraft $draft, Collection $stocks, Collection $reservations): InventoryOperation
    {
        $now = Carbon::now();

        $operation = InventoryOperation::query()->create([
            'number' => InventoryOperation::nextNumber(),
            'type' => $draft->type,
            'warehouse_id' => $draft->warehouseId,
            'reference_type' => $draft->referenceType,
            'reference_id' => $draft->referenceId,
            'reverses_operation_id' => $draft->reversesOperationId,
            'reason_code' => $draft->reasonCode,
            'notes' => $draft->notes,
            'idempotency_key' => $draft->idempotencyKey,
            'performed_by' => $draft->performedBy,
            'performed_at' => $now,
        ]);

        // Sin posición indicada, cada línea es una posición en el orden del
        // borrador; se numera antes de reordenar las líneas de una reversión.
        $lines = array_map(
            fn (MovementLine $line, int $index): array => [$line, $line->itemNumber ?? $index + 1],
            $draft->lines,
            array_keys($draft->lines),
        );

        if ($draft->type === OperationType::Reversal) {
            usort($lines, fn (array $a, array $b): int => $a[0]->reversesMovementId <=> $b[0]->reversesMovementId);
        }

        $movements = new Collection;

        foreach ($lines as [$line, $itemNumber]) {
            $stock = self::stockOf($stocks, $line);
            $reservation = $line->reservationId !== null ? $reservations->get($line->reservationId) : null;

            $stock->quantity_on_hand += $line->delta;
            $stock->last_movement_at = $now;

            if ($reservation !== null) {
                // La línea resuelve la reserva entera: consume lo retirado y
                // libera el resto (stock_reservations_active_unconsumed_check).
                $stock->quantity_reserved -= $reservation->quantity;
                $reservation->forceFill([
                    'status' => ReservationStatus::Consumed,
                    'quantity_consumed' => -$line->delta,
                    'consumed_by_operation_id' => $operation->id,
                    'resolved_at' => $now,
                ])->save();
            }

            $stock->save();

            $movements->push(StockMovement::query()->create([
                'operation_id' => $operation->id,
                'item_number' => $itemNumber,
                'warehouse_id' => $draft->warehouseId,
                'stock_id' => $stock->id,
                'product_id' => $stock->product_id,
                'location_id' => $stock->location_id,
                'movement_code' => $line->movementCode,
                'delta' => $line->delta,
                'balance_after' => $stock->quantity_on_hand,
                'reservation_id' => $line->reservationId,
                'reverses_movement_id' => $line->reversesMovementId,
            ]));
        }

        return $operation->setRelation('movements', $movements);
    }

    /**
     * @param  Collection<string, Stock>  $stocks
     */
    private static function stockOf(Collection $stocks, MovementLine $line): Stock
    {
        return $stocks->get(self::stockKey($line->productId, $line->locationId))
            ?? throw new LogicException('La existencia de la línea no quedó bloqueada.');
    }

    private static function stockKey(int $productId, int $locationId): string
    {
        return $productId.':'.$locationId;
    }
}
