<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Supplier;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;

/**
 * Listado de proveedores. name por fragmento con su índice trigram, tax_id
 * exacto con su índice único (se normaliza igual que al guardar, para que
 * 30-71234567-1 encuentre 30712345671) e is_active. Incluye los inactivos,
 * que se consultan (REG-018).
 */
class SupplierListRequest extends ListRequest
{
    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter');

        if (is_array($filter) && is_string($filter['tax_id'] ?? null)) {
            $filter['tax_id'] = Supplier::normalizeTaxId($filter['tax_id']);
            $this->merge(['filter' => $filter]);
        }
    }

    protected function filters(): array
    {
        return [
            'name' => FilterMode::Partial,
            'tax_id' => FilterMode::Exact,
            'is_active' => FilterMode::Exact,
        ];
    }

    protected function sorts(): array
    {
        return ['name', 'tax_id'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filterValueRules(): array
    {
        return [
            'is_active' => ['boolean'],
        ];
    }
}
