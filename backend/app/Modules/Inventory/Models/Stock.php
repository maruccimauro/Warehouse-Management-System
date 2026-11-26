<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouse\Models\Concerns\ScopedByWarehouse;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Existencia de un artículo en una ubicación (y en un lote, cuando existan).
 * Solo StockLedger la escribe; las restricciones CHECK de stocks son la
 * última red de INV-001 e INV-005.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $location_id
 * @property int $product_id
 * @property int|null $lot_id
 * @property int $quantity_on_hand
 * @property int $quantity_reserved
 * @property int $quantity_blocked
 * @property Carbon|null $last_movement_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int $quantity_available
 */
#[Fillable(['warehouse_id', 'location_id', 'product_id', 'lot_id', 'quantity_on_hand', 'quantity_reserved', 'quantity_blocked', 'last_movement_at'])]
class Stock extends Model
{
    use ScopedByWarehouse;

    /**
     * Valores por defecto de la base, para que un registro recién creado los
     * tenga en memoria.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
        'quantity_blocked' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_blocked' => 'integer',
            'last_movement_at' => 'datetime',
        ];
    }

    /**
     * Libre para retirar sin reserva: físico menos reservado y bloqueado.
     *
     * @return Attribute<int, never>
     */
    protected function quantityAvailable(): Attribute
    {
        return Attribute::get(fn (): int => $this->quantity_on_hand - $this->quantity_reserved - $this->quantity_blocked);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<StockReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * @return HasMany<StockBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(StockBlock::class);
    }
}
