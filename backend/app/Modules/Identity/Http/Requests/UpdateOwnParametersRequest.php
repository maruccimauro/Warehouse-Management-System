<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parámetros de usuario de SU3 (PUT /me/parameters, E2.21). Reemplazo
 * completo: cada ID admitido es obligatorio en el cuerpo y null lo borra.
 * LGN debe ser el código de un depósito activo donde el usuario opera
 * (REG-005), los mismos que ofrece SU3.
 */
class UpdateOwnParametersRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'LGN' => ['present', 'nullable', 'string', Rule::in($this->operableWarehouseCodes())],
        ];
    }

    /**
     * @return array{LGN: string|null}
     */
    public function parameters(): array
    {
        $lgn = $this->validated('LGN');

        return ['LGN' => is_string($lgn) ? $lgn : null];
    }

    /**
     * @return list<string>
     */
    private function operableWarehouseCodes(): array
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return [];
        }

        return array_values(app(WarehouseAccess::class)
            ->operableWarehouses($user)
            ->map(fn (Warehouse $warehouse): string => $warehouse->code)
            ->all());
    }
}
