<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Supplier $resource
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array{id: int, number: string, tax_id: string, name: string, email: string|null, phone: string|null, is_active: bool, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'number' => Supplier::formatNumber($this->resource->number),
            'tax_id' => $this->resource->tax_id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at->toJSON(),
            'updated_at' => $this->resource->updated_at->toJSON(),
        ];
    }
}
