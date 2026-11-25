<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Listado de productos. Cada filtro tiene su índice: sku como criterio de
 * pantalla de selección (valores, rangos y patrones con *, que la ayuda F4 usa
 * como *fragmento*) y name por fragmento, ambos con los índices trigram
 * (ILIKE '%texto%' no usa un B-tree); barcode exacto con su índice único para
 * el escaneo, y category_id con su índice. La categoría filtra solo esa
 * categoría, sin las subcategorías, como la selección por grupo de artículos
 * de SAP. status admite varios valores (lista de productos MM60, E2.13).
 */
class ProductListRequest extends ListRequest
{
    protected function filters(): array
    {
        return [
            'sku' => FilterMode::Selection,
            'name' => FilterMode::Partial,
            'barcode' => FilterMode::Exact,
            'category_id' => FilterMode::Exact,
            'status' => FilterMode::In,
        ];
    }

    protected function sorts(): array
    {
        return ['sku', 'name'];
    }

    protected function defaultSort(): string
    {
        return 'sku';
    }

    protected function filterValueRules(): array
    {
        return [
            'category_id' => ['integer', 'min:1'],
            'status' => [Rule::enum(ProductStatus::class)],
        ];
    }
}
