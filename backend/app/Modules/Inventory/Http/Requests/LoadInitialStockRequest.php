<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Actions\LoadInitialStock;
use App\Modules\Inventory\Data\AdjustmentItem;
use App\Modules\Inventory\Http\Requests\Concerns\ReadsOperationBody;
use App\Modules\Inventory\Models\InventoryOperation;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /inventory/initial-loads (E3.06). El motivo es siempre initial_load y
 * las cantidades son positivas. Las ubicaciones se validan contra el depósito
 * del cuerpo; la ubicación de sistema la valida LoadInitialStock con su
 * código (REG-014).
 */
class LoadInitialStockRequest extends FormRequest
{
    use ReadsOperationBody;

    public function authorize(): Response
    {
        return Gate::inspect('initialLoad', [InventoryOperation::class, $this->warehouseId()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'min:1', Rule::exists('warehouses', 'id')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'items' => ['required', 'list', 'min:1', 'max:'.LoadInitialStock::MAX_ITEMS],
            'items.*' => ['required', 'array:product_id,location_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'min:1', Rule::exists('products', 'id')],
            'items.*.location_id' => ['required', 'integer', 'min:1', Rule::exists('locations', 'id')->where('warehouse_id', $this->warehouseId() ?? 0)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Cada existencia (artículo y ubicación) va en una sola posición: el libro
     * admite una línea por existencia en cada operación (INV-004).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $seen = [];

                foreach ($this->items() as $index => $item) {
                    $key = $item->productId.':'.$item->locationId;

                    if (isset($seen[$key])) {
                        $validator->errors()->add("items.{$index}", __('inventory.duplicate_stock_in_items', ['item' => $index + 1, 'other' => $seen[$key] + 1]));

                        return;
                    }

                    $seen[$key] = $index;
                }
            },
        ];
    }

    /**
     * @return list<AdjustmentItem>
     */
    public function items(): array
    {
        /** @var list<array{product_id: int|string, location_id: int|string, quantity: int|string}> $items */
        $items = $this->input('items');

        return array_map(fn (array $item): AdjustmentItem => new AdjustmentItem(
            (int) $item['product_id'],
            (int) $item['location_id'],
            (int) $item['quantity'],
        ), $items);
    }
}
