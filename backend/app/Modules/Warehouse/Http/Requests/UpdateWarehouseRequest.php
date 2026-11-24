<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Nombre, dirección y activación (REG-015). El código no se modifica (DM-19):
 * va en etiquetas y documentos, como el centro (WERKS) de SAP.
 */
class UpdateWarehouseRequest extends FormRequest
{
    use ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->warehouse());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:'.StoreWarehouseRequest::ADDRESS_MAX_LENGTH],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
