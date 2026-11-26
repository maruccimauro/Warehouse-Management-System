<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un artículo con sus datos en el depósito de la lista, leído por
 * WarehouseProductSettingListRequest con las columnas del depósito unidas. Sin fila en el
 * depósito, los valores son los por defecto: stock mínimo 0 y sin umbrales.
 *
 * @property Product $resource
 */
class WarehouseProductSettingResource extends JsonResource
{
    /**
     * @return array{product_id: int, sku: string, name: string, status: string, warehouse_id: int, min_stock: int, adjustment_in_approval_threshold: int|null, adjustment_out_approval_threshold: int|null}
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'product_id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'name' => $this->resource->name,
            'status' => $this->resource->status->value,
            'warehouse_id' => self::integer($attributes['warehouse_id'] ?? null) ?? 0,
            'min_stock' => self::integer($attributes['min_stock'] ?? null) ?? 0,
            'adjustment_in_approval_threshold' => self::integer($attributes['adjustment_in_approval_threshold'] ?? null),
            'adjustment_out_approval_threshold' => self::integer($attributes['adjustment_out_approval_threshold'] ?? null),
        ];
    }

    private static function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
