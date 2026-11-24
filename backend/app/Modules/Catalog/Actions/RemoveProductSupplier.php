<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Quita el vínculo entre un producto y un proveedor (DM-22). El borrado es
 * físico porque el vínculo es configuración: ningún documento lo referencia.
 * Las órdenes de compra ya aprobadas no se ven afectadas; una orden en
 * borrador con ese producto se rechaza al enviarla a aprobación (REG-019).
 *
 * @throws ModelNotFoundException si el producto no está vinculado al proveedor
 */
final class RemoveProductSupplier
{
    public function __invoke(Supplier $supplier, Product $product): void
    {
        $deleted = ProductSupplier::query()
            ->where('product_id', $product->id)
            ->where('supplier_id', $supplier->id)
            ->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException)->setModel(ProductSupplier::class);
        }
    }
}
