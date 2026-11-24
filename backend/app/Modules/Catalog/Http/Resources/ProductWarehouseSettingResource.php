<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductWarehouseSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProductWarehouseSetting $resource
 */
class ProductWarehouseSettingResource extends JsonResource
{
    /**
     * @return array{product_id: int, warehouse_id: int, min_stock: int, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->resource->product_id,
            'warehouse_id' => $this->resource->warehouse_id,
            'min_stock' => $this->resource->min_stock,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
