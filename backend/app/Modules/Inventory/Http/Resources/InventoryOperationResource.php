<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\InventoryOperation;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Operación de inventario con sus líneas ordenadas por posición y dirección
 * (salida antes que entrada). La usan las operaciones manuales (E3.04 en
 * adelante) y la consulta de operaciones (E3.12).
 *
 * @property InventoryOperation $resource
 */
class InventoryOperationResource extends JsonResource
{
    /**
     * Relaciones que el recurso muestra: líneas por posición, la salida antes
     * que la entrada, con el SKU y el código de ubicación. Con prefijo, para
     * cargarlas desde un documento ('operations.').
     *
     * @return array<int|string, string|(Closure(Relation<*, *, *>): mixed)>
     */
    public static function relations(string $prefix = ''): array
    {
        return [
            $prefix.'movements' => fn (Relation $query) => $query->orderBy('item_number')->orderByDesc('direction')->orderBy('id'),
            $prefix.'movements.product:id,sku',
            $prefix.'movements.location:id,code',
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
            'type' => $this->resource->type->value,
            'warehouse_id' => $this->resource->warehouse_id,
            'reason_code' => $this->resource->reason_code?->value,
            'reference_type' => $this->resource->reference_type,
            'reference_id' => $this->resource->reference_id,
            'reverses_operation_id' => $this->resource->reverses_operation_id,
            'notes' => $this->resource->notes,
            'performed_by' => $this->resource->performed_by,
            'performed_at' => $this->resource->performed_at->toJSON(),
            'movements' => StockMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}
