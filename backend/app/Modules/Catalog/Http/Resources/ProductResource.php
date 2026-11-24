<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * is_lot_tracked se expone solo para lectura: los lotes están fuera del
 * alcance y la API no lo escribe.
 *
 * @property Product $resource
 */
class ProductResource extends JsonResource
{
    /**
     * @return array{id: int, sku: string, barcode: string|null, name: string, description: string|null, category_id: int|null, base_uom_id: int, weight_grams: int|null, length_mm: int|null, width_mm: int|null, height_mm: int|null, status: string, is_lot_tracked: bool, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'barcode' => $this->resource->barcode,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'category_id' => $this->resource->category_id,
            'base_uom_id' => $this->resource->base_uom_id,
            'weight_grams' => $this->resource->weight_grams,
            'length_mm' => $this->resource->length_mm,
            'width_mm' => $this->resource->width_mm,
            'height_mm' => $this->resource->height_mm,
            'status' => $this->resource->status->value,
            'is_lot_tracked' => $this->resource->is_lot_tracked,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
