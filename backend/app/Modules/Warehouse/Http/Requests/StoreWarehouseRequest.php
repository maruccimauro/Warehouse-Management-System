<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Http\Requests\Concerns\NormalizesCodes;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Alta de depósito con warehouses.manage. CreateWarehouse agrega sus zonas y
 * ubicaciones de sistema; el depósito nace activo.
 */
class StoreWarehouseRequest extends FormRequest
{
    use NormalizesCodes;

    public const ADDRESS_MAX_LENGTH = 500;

    public function authorize(): Response
    {
        return Gate::inspect('create', Warehouse::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCodes(['code']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:'.self::CODE_PATTERN, Rule::unique('warehouses', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:'.self::ADDRESS_MAX_LENGTH],
        ];
    }
}
