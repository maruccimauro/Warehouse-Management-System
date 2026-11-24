<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Relación de un producto con un proveedor, como el registro info de compras
 * (EINA y EINE) de SAP (DM-22): el código con que el proveedor identifica el
 * producto y su plazo de entrega. Una orden de compra solo admite productos
 * vinculados a su proveedor (REG-019, desde E4.01).
 *
 * La clave es compuesta (product_id, supplier_id), que Eloquent no admite: el
 * modelo sirve para leer, y SetProductSupplier escribe con upsert.
 *
 * @property int $product_id
 * @property int $supplier_id
 * @property string|null $supplier_sku
 * @property int|null $lead_time_days
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['product_id', 'supplier_id', 'supplier_sku', 'lead_time_days'])]
#[Table('product_supplier', key: false, incrementing: false)]
class ProductSupplier extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['lead_time_days' => 'integer'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
