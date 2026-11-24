<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property UnitOfMeasure $resource
 */
class UnitOfMeasureResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
        ];
    }
}
