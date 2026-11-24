<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;

/**
 * Listado de categorías. filter[parent_id] trae las hijas directas de una
 * categoría; el árbol completo cabe en pocas páginas.
 */
class CategoryListRequest extends ListRequest
{
    protected function filters(): array
    {
        return [
            'name' => FilterMode::Partial,
            'parent_id' => FilterMode::Exact,
        ];
    }

    protected function sorts(): array
    {
        return ['name'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filterValueRules(): array
    {
        return [
            'parent_id' => ['integer', 'min:1'],
        ];
    }
}
