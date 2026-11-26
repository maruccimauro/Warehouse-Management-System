<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Datos de un producto en un depósito (DM-21) con catalog.manage y el alcance
 * de operación sobre ese depósito (REG-005). El stock mínimo y los umbrales
 * de aprobación de ajustes (E3.05) son cantidades en la unidad base, enteras y
 * dentro del rango exacto de un number de JavaScript (ADR-005). Es un PUT: los
 * umbrales son obligatorios en el cuerpo y null significa "sin umbral"
 * (decisión del usuario del 26/11/2026).
 */
class UpdateProductWarehouseSettingsRequest extends FormRequest
{
    use ResolvesCatalogRouteModels;

    public const MAX_QUANTITY = 9007199254740991;

    public function authorize(): Response
    {
        return Gate::inspect('updateWarehouseSettings', [$this->product(), $this->warehouse()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'min_stock' => ['required', 'integer', 'min:0', 'max:'.self::MAX_QUANTITY],
            'adjustment_in_approval_threshold' => ['present', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'adjustment_out_approval_threshold' => ['present', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    public function threshold(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
