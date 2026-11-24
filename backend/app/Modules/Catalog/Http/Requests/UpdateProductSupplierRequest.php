<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Vínculo de un producto con un proveedor (DM-22), con suppliers.manage. El
 * PUT reemplaza el vínculo completo: un campo ausente queda nulo.
 *
 * El código del proveedor se guarda tal como lo usa el proveedor, sin pasarlo
 * a mayúsculas, y es único dentro de ese proveedor: si ACME ya usa A-100 para
 * un producto, no puede usarlo para otro. El plazo de entrega está en días
 * corridos, hasta un año.
 */
class UpdateProductSupplierRequest extends FormRequest
{
    use ResolvesCatalogRouteModels;

    public const LEAD_TIME_MAX_DAYS = 365;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->supplier());
    }

    protected function prepareForValidation(): void
    {
        $supplierSku = $this->input('supplier_sku');

        if (is_string($supplierSku)) {
            $supplierSku = trim($supplierSku);
            $this->merge(['supplier_sku' => $supplierSku === '' ? null : $supplierSku]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:60',
                Rule::unique('product_supplier', 'supplier_sku')->where(
                    fn (Builder $query): Builder => $query
                        ->where('supplier_id', $this->supplier()->id)
                        ->where('product_id', '<>', $this->product()->id),
                ),
            ],
            'lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::LEAD_TIME_MAX_DAYS],
        ];
    }

    public function supplierSku(): ?string
    {
        $value = $this->validated('supplier_sku');

        return is_string($value) ? $value : null;
    }

    public function leadTimeDays(): ?int
    {
        $value = $this->validated('lead_time_days');

        return is_numeric($value) ? (int) $value : null;
    }
}
