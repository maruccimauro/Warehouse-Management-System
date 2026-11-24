<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductSupplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vínculo producto proveedor (DM-22). Lleva solo los identificadores: la
 * grilla resuelve producto y proveedor con sus propios recursos.
 *
 * @property ProductSupplier $resource
 */
class ProductSupplierResource extends JsonResource
{
    /**
     * @return array{product_id: int, supplier_id: int, supplier_sku: string|null, lead_time_days: int|null, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->resource->product_id,
            'supplier_id' => $this->resource->supplier_id,
            'supplier_sku' => $this->resource->supplier_sku,
            'lead_time_days' => $this->resource->lead_time_days,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
