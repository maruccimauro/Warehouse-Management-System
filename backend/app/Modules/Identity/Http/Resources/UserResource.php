<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario en la administración (SU01). roles son los identificadores de los
 * roles asignados y warehouses los depósitos asignados en user_warehouse,
 * incluidos los inactivos; el alcance efectivo con warehouses.operate_all lo
 * resuelve WarehouseAccess. Requiere las relaciones roles y warehouses
 * cargadas.
 *
 * @property User $resource
 */
class UserResource extends JsonResource
{
    /**
     * @return array{id: int, username: string, name: string, email: string, locale: string, is_active: bool, password_change_required: bool, roles: list<string>, warehouses: list<array{id: int, code: string, name: string, is_active: bool}>, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        $roles = $this->resource->roles->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->values()->all();
        sort($roles);

        return [
            'id' => $this->resource->id,
            'username' => $this->resource->username,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'locale' => $this->resource->locale,
            'is_active' => $this->resource->is_active,
            'password_change_required' => $this->resource->password_change_required,
            'roles' => $roles,
            'warehouses' => array_values($this->resource->warehouses
                ->sortBy('code')
                ->map(fn (Warehouse $warehouse): array => [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'is_active' => $warehouse->is_active,
                ])
                ->all()),
            'created_at' => $this->resource->created_at?->toJSON() ?? '',
            'updated_at' => $this->resource->updated_at?->toJSON() ?? '',
        ];
    }
}
