<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Warehouse\Models\Concerns\ScopedByWarehouse;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Datos de un producto propios de un depósito, como los datos de centro (MARC)
 * de SAP (DM-21). Sin fila, el producto usa los valores por defecto en ese
 * depósito: stock mínimo 0, sin alerta de stock crítico.
 *
 * La clave es compuesta (product_id, warehouse_id), que Eloquent no admite:
 * el modelo sirve para leer, y SetProductWarehouseSettings escribe con upsert.
 *
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $min_stock
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['product_id', 'warehouse_id', 'min_stock'])]
#[Table('product_warehouse_settings', key: false, incrementing: false)]
class ProductWarehouseSetting extends Model
{
    use ScopedByWarehouse;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'integer'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
