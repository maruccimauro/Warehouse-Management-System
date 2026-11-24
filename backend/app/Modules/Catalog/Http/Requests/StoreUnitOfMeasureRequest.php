<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Shared\Http\Requests\Concerns\NormalizesCodes;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Alta de unidad de medida con catalog.manage; el código se guarda en
 * mayúsculas y no cambia después del alta.
 */
class StoreUnitOfMeasureRequest extends FormRequest
{
    use NormalizesCodes;

    public function authorize(): Response
    {
        return Gate::inspect('create', UnitOfMeasure::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCodes(['code']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'regex:'.self::CODE_PATTERN, Rule::unique('units_of_measure', 'code')],
            'name' => ['required', 'string', 'max:60'],
        ];
    }
}
