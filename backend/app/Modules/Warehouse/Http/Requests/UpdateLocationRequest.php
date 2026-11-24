<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Zone;
use App\Shared\Http\Requests\Concerns\NormalizesCodes;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * El código no se modifica (no se acepta). El estado solo admite active e
 * inactive (activación y desactivación); el bloqueo tiene su propia operación
 * (E2.04). Una ubicación de sistema no cambia de zona ni de estado por acá.
 */
class UpdateLocationRequest extends FormRequest
{
    use NormalizesCodes, ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->location());
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCodes(['aisle', 'rack', 'level', 'position']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $location = $this->location();

        return [
            'code' => ['prohibited'],
            'status' => $location->is_system
                ? ['prohibited']
                : ['sometimes', 'required', Rule::in([LocationStatus::Active->value, LocationStatus::Inactive->value])],
            'zone_id' => $location->is_system
                ? ['prohibited']
                : ['sometimes', 'required', 'integer', StoreLocationRequest::userZoneOf($location->warehouse_id)],
            ...StoreLocationRequest::coordinateRules(),
        ];
    }

    public function location(): Location
    {
        return $this->routeLocation();
    }

    public function zone(): ?Zone
    {
        return $this->has('zone_id')
            ? Zone::query()->where('warehouse_id', $this->location()->warehouse_id)->findOrFail($this->integer('zone_id'))
            : null;
    }
}
