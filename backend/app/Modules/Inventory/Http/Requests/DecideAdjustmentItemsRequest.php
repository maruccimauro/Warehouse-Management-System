<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\ResolvesStockAdjustment;
use App\Modules\Inventory\Services\AdjustmentItemDecision;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Cuerpo común de la aprobación y el rechazo de posiciones de ajuste (E3.23,
 * D13 del plan de E3): los números de posición del documento, distintos, y
 * las notas de la decisión. Una posición que no es del documento es 422; una
 * que ya no está pendiente la responde la Action con 409.
 */
abstract class DecideAdjustmentItemsRequest extends FormRequest
{
    use ResolvesStockAdjustment;

    public function authorize(): Response
    {
        return Gate::inspect('decide', $this->adjustment());
    }

    /**
     * @return list<mixed>
     */
    abstract protected function notesRules(): array;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'list', 'min:1', 'max:'.AdjustmentItemDecision::MAX_ITEMS],
            'items.*' => [
                'required',
                'integer',
                'min:1',
                'distinct',
                Rule::exists('stock_adjustment_items', 'item_number')->where('adjustment_id', $this->adjustment()->id),
            ],
            'notes' => $this->notesRules(),
        ];
    }

    /**
     * @return list<int>
     */
    public function itemNumbers(): array
    {
        /** @var list<int|string> $items */
        $items = $this->validated('items');

        return array_map(intval(...), $items);
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
    }
}
