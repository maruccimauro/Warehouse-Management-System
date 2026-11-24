<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Category $resource
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array{id: int, parent_id: int|null, name: string, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'parent_id' => $this->resource->parent_id,
            'name' => $this->resource->name,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
