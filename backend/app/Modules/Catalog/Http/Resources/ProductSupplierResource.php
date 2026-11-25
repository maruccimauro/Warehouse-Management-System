<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductSupplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vínculo producto proveedor (DM-22). Los listados suman el resumen del otro
 * extremo, cargado con with() en la misma consulta: el producto en los
 * productos de un proveedor y el proveedor en los proveedores de un producto,
 * para que la grilla muestre SKU y nombre sin una lectura por fila (E2.13).
 *
 * @property ProductSupplier $resource
 */
class ProductSupplierResource extends JsonResource
{
    /**
     * @return array{product_id: int, supplier_id: int, product?: array{sku: string, name: string, status: string}, supplier?: array{tax_id: string, name: string, is_active: bool}, supplier_sku: string|null, lead_time_days: int|null, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        $link = $this->resource;
        $data = [
            'product_id' => $link->product_id,
            'supplier_id' => $link->supplier_id,
        ];

        $product = $link->relationLoaded('product') ? $link->product : null;
        if ($product !== null) {
            $data['product'] = ['sku' => $product->sku, 'name' => $product->name, 'status' => $product->status->value];
        }

        $supplier = $link->relationLoaded('supplier') ? $link->supplier : null;
        if ($supplier !== null) {
            $data['supplier'] = ['tax_id' => $supplier->tax_id, 'name' => $supplier->name, 'is_active' => $supplier->is_active];
        }

        return [
            ...$data,
            'supplier_sku' => $link->supplier_sku,
            'lead_time_days' => $link->lead_time_days,
            'created_at' => $link->created_at->toJSON(),
            'updated_at' => $link->updated_at->toJSON(),
        ];
    }
}
