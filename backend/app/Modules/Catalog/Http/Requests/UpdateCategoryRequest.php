<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Renombre y cambio de padre (parent_id null la pasa a raíz). La unicidad del
 * nombre se valida contra el padre resultante: si la petición mueve la
 * categoría sin renombrarla, se valida su nombre actual contra las nuevas
 * hermanas, para que el choque sea 422 y no un error de la base.
 */
class UpdateCategoryRequest extends FormRequest
{
    use ResolvesCatalogRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->category());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        } elseif ($this->has('parent_id') && ! $this->has('name')) {
            $this->merge(['name' => $this->category()->name]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $category = $this->category();
        $parentId = $this->has('parent_id') ? $this->input('parent_id') : $category->parent_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', StoreCategoryRequest::uniqueNameUnder($parentId)->ignore($category->id)],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('categories', 'id')],
        ];
    }
}
