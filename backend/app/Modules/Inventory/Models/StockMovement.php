<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Models\Concerns\WrittenOnlyByStockLedger;
use App\Modules\Warehouse\Models\Concerns\ScopedByWarehouse;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Línea del libro de movimientos: delta firmado sobre una existencia, con su
 * código de movimiento (DM-09) y el saldo resultante. Es de solo inserción
 * (INV-003). direction es una columna generada a partir del signo de delta.
 *
 * @property int $id
 * @property int $operation_id
 * @property int $item_number
 * @property int $warehouse_id
 * @property int $stock_id
 * @property int $product_id
 * @property int $location_id
 * @property int $movement_code
 * @property int $delta
 * @property MovementDirection $direction
 * @property int $balance_after
 * @property int|null $reservation_id
 * @property int|null $reverses_movement_id
 * @property Carbon $created_at
 */
#[Fillable(['operation_id', 'item_number', 'warehouse_id', 'stock_id', 'product_id', 'location_id', 'movement_code', 'delta', 'balance_after', 'reservation_id', 'reverses_movement_id'])]
class StockMovement extends Model
{
    use ScopedByWarehouse, WrittenOnlyByStockLedger;

    /** La línea no se modifica: solo tiene fecha de alta. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_number' => 'integer',
            'movement_code' => 'integer',
            'delta' => 'integer',
            'direction' => MovementDirection::class,
            'balance_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InventoryOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(InventoryOperation::class, 'operation_id');
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<StockReservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(StockReservation::class, 'reservation_id');
    }

    /**
     * Línea original que esta revierte.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_movement_id');
    }

    public function movementType(): ?MovementType
    {
        return MovementType::findByCode($this->movement_code, $this->direction);
    }
}
