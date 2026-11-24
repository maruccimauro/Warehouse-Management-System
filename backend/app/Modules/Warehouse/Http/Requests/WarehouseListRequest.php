<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Auth\AuthenticationException;

/**
 * Listado general de depósitos: no hay recurso padre que autorizar, y el
 * controlador filtra con visibleTo (REG-005). Incluye los inactivos, que se
 * consultan (REG-015); filter[is_active] los separa.
 */
class WarehouseListRequest extends ListRequest
{
    protected function filters(): array
    {
        return [
            'code' => FilterMode::Partial,
            'name' => FilterMode::Partial,
            'is_active' => FilterMode::Exact,
        ];
    }

    protected function sorts(): array
    {
        return ['code', 'name'];
    }

    protected function defaultSort(): string
    {
        return 'code';
    }

    protected function filterValueRules(): array
    {
        return [
            'is_active' => ['boolean'],
        ];
    }

    public function viewer(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
