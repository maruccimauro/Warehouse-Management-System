<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Data\TransferItem;
use App\Modules\Inventory\Http\Requests\Concerns\ReadsOperationBody;
use App\Modules\Inventory\Models\InventoryOperation;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /inventory/transfers (E3.04). Las ubicaciones se validan contra el
 * depósito del cuerpo: una de otro depósito es un error de su campo. Las
 * reglas de negocio (origen igual al destino, destino de sistema) las valida
 * TransferStock con su código.
 */
class TransferStockRequest extends FormRequest
{
    use ReadsOperationBody;

    public function authorize(): Response
    {
        return Gate::inspect('transfer', [InventoryOperation::class, $this->warehouseId()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $location = Rule::exists('locations', 'id')->where('warehouse_id', $this->warehouseId() ?? 0);

        return [
            'warehouse_id' => ['required', 'integer', 'min:1', Rule::exists('warehouses', 'id')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'items' => ['required', 'list', 'min:1', 'max:'.TransferStock::MAX_ITEMS],
            'items.*' => ['required', 'array:product_id,from_location_id,to_location_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'min:1', Rule::exists('products', 'id')],
            'items.*.from_location_id' => ['required', 'integer', 'min:1', $location],
            'items.*.to_location_id' => ['required', 'integer', 'min:1', $location],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Cada existencia (artículo y ubicación) se mueve en una sola posición: el
     * libro admite una línea por existencia en cada operación (INV-004).
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

                /** @var list<array{product_id: int|string, from_location_id: int|string, to_location_id: int|string, quantity: int|string}> $raw */
                $raw = $this->input('items');

                foreach (self::toItems($raw) as $index => $item) {
                    foreach ([$item->fromLocationId, $item->toLocationId] as $locationId) {
                        $key = $item->productId.':'.$locationId;

                        if (isset($seen[$key]) && $seen[$key] !== $index) {
                            $validator->errors()->add("items.{$index}", __('inventory.duplicate_stock_in_items', ['item' => $index + 1, 'other' => $seen[$key] + 1]));

                            return;
                        }

                        $seen[$key] = $index;
                    }
                }
            },
        ];
    }

    /**
     * @return list<TransferItem>
     */
    public function items(): array
    {
        /** @var list<array{product_id: int|string, from_location_id: int|string, to_location_id: int|string, quantity: int|string}> $items */
        $items = $this->validated('items');

        return self::toItems($items);
    }

    /**
     * @param  list<array{product_id: int|string, from_location_id: int|string, to_location_id: int|string, quantity: int|string}>  $items
     * @return list<TransferItem>
     */
    private static function toItems(array $items): array
    {
        return array_map(fn (array $item): TransferItem => new TransferItem(
            (int) $item['product_id'],
            (int) $item['from_location_id'],
            (int) $item['to_location_id'],
            (int) $item['quantity'],
        ), $items);
    }
}
