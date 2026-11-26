<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Data\AdjustmentItem;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Http\Requests\Concerns\ReadsOperationBody;
use App\Modules\Inventory\Models\InventoryOperation;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /inventory/adjustments (E3.05, CU-04). La dirección decide los motivos
 * admitidos (INV-008): found y correction suman; damage, loss y correction
 * restan. Las cantidades son positivas: el signo lo da la dirección. Las
 * ubicaciones se validan contra el depósito del cuerpo; la ubicación de
 * sistema la valida AdjustStock con su código.
 */
class AdjustStockRequest extends FormRequest
{
    use ReadsOperationBody;

    public function authorize(): Response
    {
        return Gate::inspect('adjust', [InventoryOperation::class, $this->warehouseId()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $direction = MovementDirection::tryFrom($this->string('direction')->value());
        $reasons = $direction === null ? [] : array_map(fn (AdjustmentReason $reason): string => $reason->value, AdjustStock::reasonsFor($direction));

        return [
            'warehouse_id' => ['required', 'integer', 'min:1', Rule::exists('warehouses', 'id')],
            'direction' => ['required', 'string', Rule::enum(MovementDirection::class)],
            'reason_code' => ['required', 'string', Rule::in($reasons)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'items' => ['required', 'list', 'min:1', 'max:'.AdjustStock::MAX_ITEMS],
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

    public function direction(): MovementDirection
    {
        return MovementDirection::from($this->string('direction')->value());
    }

    public function reason(): AdjustmentReason
    {
        return AdjustmentReason::from($this->string('reason_code')->value());
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
