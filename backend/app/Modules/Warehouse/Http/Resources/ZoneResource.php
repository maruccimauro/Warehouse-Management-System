<?php

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Warehouse\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Zone $resource
 */
class ZoneResource extends JsonResource
{
    /**
     * @return array{id: int, warehouse_id: int, code: string, name: string, type: string, is_system: bool, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'warehouse_id' => $this->resource->warehouse_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'type' => $this->resource->type->value,
            'is_system' => $this->resource->is_system,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
