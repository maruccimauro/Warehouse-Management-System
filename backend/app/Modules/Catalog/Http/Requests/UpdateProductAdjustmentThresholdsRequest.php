<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Umbrales de aprobación de ajustes de un artículo en un depósito (REG-024)
 * con inventory.thresholds.manage. Es un PUT: los dos umbrales son
 * obligatorios en el cuerpo y null significa "sin umbral" (decisión del
 * usuario del 26/11/2026). Cantidades en la unidad base, mayores que cero y
 * dentro del rango exacto de un number de JavaScript (ADR-005).
 */
class UpdateProductAdjustmentThresholdsRequest extends FormRequest
{
    use ResolvesCatalogRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('updateAdjustmentThresholds', [$this->product(), $this->warehouse()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $max = 'max:'.UpdateProductMinStockRequest::MAX_QUANTITY;

        return [
            'adjustment_in_approval_threshold' => ['present', 'nullable', 'integer', 'min:1', $max],
            'adjustment_out_approval_threshold' => ['present', 'nullable', 'integer', 'min:1', $max],
        ];
    }

    public function threshold(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
