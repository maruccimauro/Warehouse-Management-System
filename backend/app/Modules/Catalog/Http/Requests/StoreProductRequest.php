<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Rules\Barcode;
use App\Shared\Http\Requests\Concerns\NormalizesCodes;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Alta de producto con catalog.manage. Nace activo (REG-017) y sin
 * seguimiento por lote; el SKU se guarda en mayúsculas y, como la unidad
 * base, no cambia después del alta (DM-20).
 */
class StoreProductRequest extends FormRequest
{
    use NormalizesCodes;

    public const DESCRIPTION_MAX_LENGTH = 2000;

    /** Máximo de una columna INTEGER de PostgreSQL. */
    public const DIMENSION_MAX = 2147483647;

    public function authorize(): Response
    {
        return Gate::inspect('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCodes(['sku']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:40', 'regex:'.self::CODE_PATTERN, Rule::unique('products', 'sku')],
            'base_uom_id' => ['required', 'integer', 'min:1', Rule::exists('units_of_measure', 'id')],
            'name' => ['required', 'string', 'max:200'],
            'status' => ['prohibited'],
            'is_lot_tracked' => ['prohibited'],
            ...self::editableRules(),
        ];
    }

    /**
     * Reglas de los campos que se cargan en el alta y se editan después.
     *
     * @return array<string, list<mixed>>
     */
    public static function editableRules(?Product $ignore = null): array
    {
        $dimension = ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::DIMENSION_MAX];

        return [
            'barcode' => ['sometimes', 'nullable', 'string', new Barcode, Rule::unique('products', 'barcode')->ignore($ignore?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX_LENGTH],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('categories', 'id')],
            'weight_grams' => $dimension,
            'length_mm' => $dimension,
            'width_mm' => $dimension,
            'height_mm' => $dimension,
        ];
    }
}
