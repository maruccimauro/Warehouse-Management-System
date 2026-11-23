<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Http\Requests\Concerns\NormalizesCodes;
use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    use NormalizesCodes, ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('create', [Location::class, $this->warehouse()]);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCodes(['code', 'aisle', 'rack', 'level', 'position']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:'.self::CODE_PATTERN, Rule::unique('locations', 'code')->where('warehouse_id', $this->warehouse()->id)],
            'zone_id' => ['required', 'integer', self::userZoneOf($this->warehouse()->id)],
            ...self::coordinateRules(),
        ];
    }

    /**
     * Zona del mismo depósito que no es de sistema.
     */
    public static function userZoneOf(int $warehouseId): mixed
    {
        return Rule::exists('zones', 'id')->where('warehouse_id', $warehouseId)->where('is_system', false);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function coordinateRules(): array
    {
        return [
            'aisle' => ['sometimes', 'nullable', 'string', 'max:10'],
            'rack' => ['sometimes', 'nullable', 'string', 'max:10'],
            'level' => ['sometimes', 'nullable', 'string', 'max:10'],
            'position' => ['sometimes', 'nullable', 'string', 'max:10'],
            'max_units' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function zone(): Zone
    {
        return $this->warehouse()->zones()->findOrFail($this->integer('zone_id'));
    }
}
