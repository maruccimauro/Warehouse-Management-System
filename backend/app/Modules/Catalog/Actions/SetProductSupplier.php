<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Shared\Exceptions\ValueAlreadyExists;

/**
 * Vincula un producto con un proveedor o reemplaza los datos del vínculo
 * (DM-22), con un único INSERT ... ON CONFLICT: dos peticiones simultáneas no
 * chocan por la clave primaria. Es mantenimiento de datos maestros: admite
 * productos discontinuados o bloqueados y proveedores inactivos, igual que los
 * datos por depósito (DM-21).
 *
 * Un código de proveedor repetido dentro del mismo proveedor lo rechaza el
 * Form Request y, ante una carrera, la restricción
 * product_supplier_supplier_id_supplier_sku_unique.
 */
final class SetProductSupplier
{
    /**
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(Supplier $supplier, Product $product, ?string $supplierSku, ?int $leadTimeDays): ProductSupplier
    {
        ProductSupplier::query()->upsert(
            [[
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'supplier_sku' => $supplierSku,
                'lead_time_days' => $leadTimeDays,
            ]],
            ['product_id', 'supplier_id'],
            ['supplier_sku', 'lead_time_days'],
        );

        return ProductSupplier::query()
            ->where('product_id', $product->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();
    }
}
