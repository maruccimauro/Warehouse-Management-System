<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Usuario autenticado con lo que la SPA necesita para armar la interfaz.
 *
 * roles y permissions salen de spatie/laravel-permission (E2.07): los
 * permisos son los efectivos, heredados de los roles o asignados directo.
 * warehouses forma parte del contrato desde E1 y se completa con la asignación
 * de depósitos (E2.08, ADR-009).
 *
 * @property User $resource
 */
class AuthenticatedUserResource extends JsonResource
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    /**
     * @return array{id: int, name: string, email: string, roles: list<string>, permissions: list<string>, warehouses: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'roles' => self::sortedNames($this->resource->getRoleNames()),
            'permissions' => self::sortedNames($this->resource->getAllPermissions()->pluck('name')),
            'warehouses' => [],
        ];
    }

    /**
     * @param  Collection<array-key, mixed>  $names
     * @return list<string>
     */
    private static function sortedNames(Collection $names): array
    {
        $strings = $names->filter(fn (mixed $name): bool => is_string($name))->all();
        sort($strings);

        return $strings;
    }
}
