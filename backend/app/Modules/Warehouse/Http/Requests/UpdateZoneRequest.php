<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * El código no se modifica (no se acepta). El tipo de una zona de sistema
 * tampoco: lo fija su rol.
 */
class UpdateZoneRequest extends FormRequest
{
    use ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->zone());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'type' => $this->zone()->is_system
                ? ['prohibited']
                : ['sometimes', 'required', Rule::enum(ZoneType::class)],
        ];
    }

    public function zone(): Zone
    {
        return $this->routeZone();
    }
}
