<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductWarehouseSetting;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Stock mínimo de un artículo en un depósito (DM-21), que mantiene el
 * supervisor del depósito con catalog.min_stock.manage. Crea la fila si no
 * existe, con los umbrales sin valor, y si existe cambia solo el stock
 * mínimo: los umbrales son de SetProductAdjustmentThresholds, con otro
 * responsable (.claude/rules/responsabilidades-por-transaccion.md). Un único
 * INSERT ... ON CONFLICT, para que dos peticiones simultáneas no choquen por
 * la clave primaria.
 *
 * Es mantenimiento de datos maestros, no una operación: no llama a
 * LockActiveWarehouse y admite depósitos inactivos. No se audita.
 */
final class SetProductMinStock
{
    public function __invoke(Product $product, Warehouse $warehouse, int $minStock): ProductWarehouseSetting
    {
        return DB::transaction(function () use ($product, $warehouse, $minStock): ProductWarehouseSetting {
            ProductWarehouseSetting::query()->upsert(
                [['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'min_stock' => $minStock]],
                ['product_id', 'warehouse_id'],
                ['min_stock'],
            );

            return ProductWarehouseSetting::of($product, $warehouse)->firstOrFail();
        });
    }
}
