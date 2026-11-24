<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Edición de un proveedor y su activación o desactivación (REG-018). El
 * identificador fiscal se puede corregir y sigue siendo único.
 */
class UpdateSupplierRequest extends StoreSupplierRequest
{
    use ResolvesCatalogRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->supplier());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_id' => ['sometimes', ...self::taxIdRules($this->supplier())],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            ...self::contactRules(),
        ];
    }
}
