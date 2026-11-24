<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Bloqueo con motivo obligatorio (domain-model.md, máquina de estados de
 * Location). El largo máximo es el de locations.status_reason.
 */
class BlockLocationRequest extends FormRequest
{
    use ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('block', $this->location());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function location(): Location
    {
        return $this->routeLocation();
    }
}
