<?php

namespace App\Modules\Warehouse\Http\Resources;

use App\Modules\Warehouse\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Location $resource
 */
class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'warehouse_id' => $this->resource->warehouse_id,
            'zone_id' => $this->resource->zone_id,
            'code' => $this->resource->code,
            'aisle' => $this->resource->aisle,
            'rack' => $this->resource->rack,
            'level' => $this->resource->level,
            'position' => $this->resource->position,
            'status' => $this->resource->status->value,
            'status_reason' => $this->resource->status_reason,
            'max_units' => $this->resource->max_units,
            'system_role' => $this->resource->system_role?->value,
            'is_system' => $this->resource->is_system,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
