<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Usuario autenticado con lo que la SPA necesita para armar la interfaz.
 *
 * roles y permissions salen de spatie/laravel-permission (E2.07): los
 * permisos son los efectivos, heredados de los roles o asignados directo.
 * warehouses son los depósitos activos donde el usuario opera (E2.08,
 * REG-005): los asignados, o todos con warehouses.operate_all. Los que solo
 * consulta no se listan acá: las pantallas de consulta los obtienen de la API
 * de depósitos, que aplica el alcance de consulta.
 *
 * @property User $resource
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @param  EloquentCollection<int, Warehouse>  $warehouses
     */
    public function __construct(User $user, private readonly EloquentCollection $warehouses)
    {
        parent::__construct($user);
    }

    /**
     * @return array{id: int, name: string, email: string, password_change_required: bool, roles: list<string>, permissions: list<string>, warehouses: list<array{id: int, code: string, name: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            // Con true, la SPA muestra el cambio de contraseña: el resto de la
            // API responde 403 PASSWORD_CHANGE_REQUIRED (REG-021).
            'password_change_required' => $this->resource->password_change_required,
            // El @var documenta el tipo en OpenAPI, que Scramble no infiere de sort() (ADR-012).
            /** @var list<string> */
            'roles' => self::sortedNames($this->resource->getRoleNames()),
            /** @var list<string> */
            'permissions' => self::sortedNames($this->resource->getAllPermissions()->pluck('name')),
            'warehouses' => array_values($this->warehouses
                ->map(fn (Warehouse $warehouse): array => [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                ])
                ->all()),
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
