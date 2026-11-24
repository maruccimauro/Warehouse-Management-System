<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Roles y sus permisos (consulta de PFCG, E2.14) con users.manage. Los roles
 * los define el código (Role) y wms:sync-authorization los lleva a la base,
 * así que la respuesta sale del catálogo y no admite edición. El nombre
 * visible de cada rol lo traduce el frontend.
 */
final class RoleController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $roles = array_map(function (Role $role): array {
            $permissions = array_map(fn ($permission): string => $permission->value, $role->permissions());
            sort($permissions);

            return ['name' => $role->value, 'permissions' => $permissions];
        }, Role::cases());

        return response()->json(['data' => $roles]);
    }
}
