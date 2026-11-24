<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Category;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Alta de categoría con catalog.manage. El nombre es único entre las hijas de
 * un mismo padre, también entre las raíces (categories_parent_id_name_unique,
 * NULLS NOT DISTINCT). Profundidad y ciclos los valida la Action (REG-016).
 */
class StoreCategoryRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', Category::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', self::uniqueNameUnder($this->input('parent_id'))],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('categories', 'id')],
        ];
    }

    /**
     * Unicidad del nombre entre las hijas de $parentId (null: entre las raíces).
     */
    public static function uniqueNameUnder(mixed $parentId): Unique
    {
        return Rule::unique('categories', 'name')->where(
            fn (Builder $query) => is_numeric($parentId) ? $query->where('parent_id', (int) $parentId) : $query->whereNull('parent_id'),
        );
    }
}
