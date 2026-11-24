<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductWarehouseSetting;
use App\Modules\Warehouse\Models\Warehouse;

/**
 * Datos de un producto en un depósito (DM-21). Crea la fila si no existe y la
 * reemplaza si existe, con un único INSERT ... ON CONFLICT: dos peticiones
 * simultáneas no chocan por la clave primaria. Es mantenimiento de datos
 * maestros, no una operación: no llama a LockActiveWarehouse y admite
 * depósitos inactivos, como las zonas y ubicaciones.
 */
final class SetProductWarehouseSettings
{
    public function __invoke(Product $product, Warehouse $warehouse, int $minStock): ProductWarehouseSetting
    {
        ProductWarehouseSetting::query()->upsert(
            [['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'min_stock' => $minStock]],
            ['product_id', 'warehouse_id'],
            ['min_stock'],
        );

        return ProductWarehouseSetting::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();
    }
}
