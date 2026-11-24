<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Solo el nombre: el código no cambia, porque las cantidades de los productos
 * que la usan como base están expresadas en ella (ADR-005).
 */
class UpdateUnitOfMeasureRequest extends FormRequest
{
    use ResolvesCatalogRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->unit());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'name' => ['required', 'string', 'max:60'],
        ];
    }
}
