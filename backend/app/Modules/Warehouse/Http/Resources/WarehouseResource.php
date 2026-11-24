<?php

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Warehouse $resource
 */
class WarehouseResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string, address: string|null, is_active: bool, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
