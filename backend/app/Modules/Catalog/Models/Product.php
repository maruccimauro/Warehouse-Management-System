<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Artículo maestro, global a todos los depósitos; lo propio de cada depósito
 * está en ProductWarehouseSetting (DM-21). El SKU y la unidad base no cambian
 * después del alta (DM-20). is_lot_tracked queda en false mientras los lotes
 * estén fuera del alcance: la API no lo escribe.
 *
 * @property int $id
 * @property string $sku
 * @property string|null $barcode
 * @property string $name
 * @property string|null $description
 * @property int|null $category_id
 * @property int $base_uom_id
 * @property int|null $weight_grams
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property ProductStatus $status
 * @property bool $is_lot_tracked
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['sku', 'barcode', 'name', 'description', 'category_id', 'base_uom_id', 'weight_grams', 'length_mm', 'width_mm', 'height_mm', 'status'])]
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_lot_tracked' => 'boolean',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
        ];
    }

    /**
     * El SKU se guarda en mayúsculas (products_sku_uppercase_check).
     *
     * @return Attribute<string, string>
     */
    protected function sku(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtoupper(trim($value)));
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    /**
     * @return HasMany<ProductWarehouseSetting, $this>
     */
    public function warehouseSettings(): HasMany
    {
        return $this->hasMany(ProductWarehouseSetting::class);
    }

    /**
     * @return HasMany<ProductSupplier, $this>
     */
    public function supplierLinks(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }
}
