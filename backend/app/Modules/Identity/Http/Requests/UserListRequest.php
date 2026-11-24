<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Listado de usuarios (SU01, consulta) con users.manage. name y email por
 * fragmento, sin índice trigram porque la tabla es chica; is_active; role por
 * su identificador y warehouse_id por depósito asignado. Incluye los
 * inactivos, que se conservan.
 */
class UserListRequest extends ListRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', User::class);
    }

    protected function filters(): array
    {
        return [
            'name' => FilterMode::Partial,
            'email' => FilterMode::Partial,
            'is_active' => FilterMode::Exact,
            'role' => FilterMode::Custom,
            'warehouse_id' => FilterMode::Custom,
        ];
    }

    protected function sorts(): array
    {
        return ['name', 'email'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filterValueRules(): array
    {
        return [
            'is_active' => ['boolean'],
            'role' => ['string', Rule::enum(Role::class)],
            'warehouse_id' => ['integer', 'min:1'],
        ];
    }

    protected function applyCustomFilter(Builder $query, string $field, mixed $value): void
    {
        $value = is_scalar($value) ? (string) $value : '';

        match ($field) {
            'role' => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $value)),
            'warehouse_id' => $query->whereHas('warehouses', fn (Builder $warehouses) => $warehouses->where('warehouses.id', (int) $value)),
            default => parent::applyCustomFilter($query, $field, $value),
        };
    }
}
