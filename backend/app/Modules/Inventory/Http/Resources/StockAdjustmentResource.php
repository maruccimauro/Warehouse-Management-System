<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentItem;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Documento de ajuste con sus posiciones y las operaciones del libro que las
 * contabilizaron. status se deriva de las posiciones: pending_approval si
 * alguna espera aprobación, completed si no.
 *
 * @property StockAdjustment $resource
 */
class StockAdjustmentResource extends JsonResource
{
    /**
     * @return array<int|string, string|(Closure(Relation<*, *, *>): mixed)>
     */
    public static function relations(): array
    {
        return [
            'items' => fn (Relation $query) => $query->orderBy('item_number'),
            'items.product:id,sku',
            'items.location:id,code',
            'items.operation:id,number',
            'operations' => fn (Relation $query) => $query->orderBy('id'),
            ...InventoryOperationResource::relations('operations.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'number' => $this->resource->formattedNumber(),
            'warehouse_id' => $this->resource->warehouse_id,
            'direction' => $this->resource->direction->value,
            'reason_code' => $this->resource->reason_code->value,
            'status' => $this->resource->hasPendingItems() ? 'pending_approval' : 'completed',
            'notes' => $this->resource->notes,
            'created_by' => $this->resource->created_by,
            'created_at' => $this->resource->created_at->toJSON(),
            'items' => $this->resource->items->map(fn (StockAdjustmentItem $item): array => [
                'item_number' => $item->item_number,
                'product_id' => $item->product_id,
                'sku' => $item->product?->sku,
                'location_id' => $item->location_id,
                'location_code' => $item->location?->code,
                'quantity' => $item->quantity,
                'approval_threshold' => $item->approval_threshold,
                'status' => $item->status->value,
                'operation_id' => $item->operation_id,
                'operation_number' => $item->operation?->formattedNumber(),
                'decided_by' => $item->decided_by,
                'decided_at' => $item->decided_at?->toJSON(),
                'decision_notes' => $item->decision_notes,
            ])->all(),
            'operations' => InventoryOperationResource::collection($this->whenLoaded('operations')),
        ];
    }
}
