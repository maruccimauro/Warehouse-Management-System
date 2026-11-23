<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario autenticado con lo que la SPA necesita para armar la interfaz.
 *
 * roles, permissions y warehouses forman parte del contrato desde E1 para que
 * el frontend no cambie; se completan en E2 con spatie/laravel-permission y la
 * asignación de depósitos (ADR-009).
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
            'roles' => [],
            'permissions' => [],
            'warehouses' => [],
        ];
    }
}
