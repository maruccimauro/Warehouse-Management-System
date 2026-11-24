<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Edición de un producto y cambio de estado (REG-017). El SKU, la unidad base
 * (DM-20) y el seguimiento por lote no se modifican.
 */
class UpdateProductRequest extends FormRequest
{
    use ResolvesCatalogRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->product());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'sku' => ['prohibited'],
            'base_uom_id' => ['prohibited'],
            'is_lot_tracked' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'status' => ['sometimes', 'required', Rule::enum(ProductStatus::class)],
            ...StoreProductRequest::editableRules($this->product()),
        ];
    }
}
