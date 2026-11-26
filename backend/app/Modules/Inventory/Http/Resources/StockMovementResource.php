<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Línea del libro. sku y location_code salen de las relaciones cuando están
 * cargadas, para que la pantalla muestre la posición sin otra consulta.
 *
 * @property StockMovement $resource
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'item_number' => $this->resource->item_number,
            'product_id' => $this->resource->product_id,
            'sku' => $this->resource->relationLoaded('product') ? $this->resource->product?->sku : null,
            'location_id' => $this->resource->location_id,
            'location_code' => $this->resource->relationLoaded('location') ? $this->resource->location?->code : null,
            'movement_code' => $this->resource->movement_code,
            'direction' => $this->resource->direction->value,
            'delta' => $this->resource->delta,
            'balance_after' => $this->resource->balance_after,
            'reservation_id' => $this->resource->reservation_id,
            'reverses_movement_id' => $this->resource->reverses_movement_id,
        ];
    }
}
