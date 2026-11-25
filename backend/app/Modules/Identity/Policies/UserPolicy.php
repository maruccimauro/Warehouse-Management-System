<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserAssignments;
use Illuminate\Auth\Access\Response;

/**
 * Usuarios y roles: consulta con users.view (ZW93, SUIM, PFCG, E2.14 D3) y
 * administración con users.manage (SU01, ZW91, ZW92). users.manage también
 * consulta, porque modificar exige leer la ficha.
 *
 * Contra la escalada de privilegios (REG-023, D4), como S_USER_GRP y
 * S_USER_AGR en SAP, sin depender del nombre de ningún rol (ADR-009):
 * - Solo se administra a un usuario cuyos permisos efectivos tiene también
 *   quien opera: de otro modo, resetear su contraseña sería tomar su cuenta.
 * - Solo se asignan o quitan roles cuyos permisos tiene quien opera.
 * - Nadie cambia sus propios roles.
 *
 * Mientras users.manage sea solo del Administrador, que tiene todos los
 * permisos, las dos primeras reglas siempre se cumplen; protegen el día en que
 * otro rol reciba users.manage en Role::permissions().
 */
final class UserPolicy
{
    public function __construct(private readonly WarehouseAccess $access) {}

    public function viewAny(User $actor): Response
    {
        return $this->authorizeView($actor);
    }

    public function view(User $actor): Response
    {
        return $this->authorizeView($actor);
    }

    public function create(User $actor): Response
    {
        return $this->access->authorizePermission($actor, Permission::UsersManage);
    }

    public function update(User $actor, User $target): Response
    {
        $permission = $this->access->authorizePermission($actor, Permission::UsersManage);

        if ($permission->denied()) {
            return $permission;
        }

        return self::covers(self::effectivePermissions($actor), self::effectivePermissions($target))
            ? Response::allow()
            : Response::deny(__('identity.target_exceeds_permissions'));
    }

    /**
     * Asignación de roles en el alta (target nulo) o en la edición. Se evalúa
     * después de validar, cuando los roles pedidos ya son valores conocidos.
     *
     * @param  list<Role>  $roles
     */
    public function assignRoles(User $actor, ?User $target, array $roles): Response
    {
        $current = $target === null ? [] : UserAssignments::currentRoleNames($target);
        $requested = array_map(fn (Role $role): string => $role->value, $roles);
        $changed = array_values(array_unique([
            ...array_diff($requested, $current),
            ...array_diff($current, $requested),
        ]));

        if ($changed === []) {
            return Response::allow();
        }

        if ($target !== null && $actor->is($target)) {
            return Response::deny(__('identity.own_roles_not_editable'));
        }

        $actorPermissions = self::effectivePermissions($actor);

        foreach ($changed as $name) {
            $role = Role::tryFrom($name);
            $required = $role === null ? [] : array_map(fn (Permission $permission): string => $permission->value, $role->permissions());

            if ($role === null || ! self::covers($actorPermissions, $required)) {
                return Response::deny(__('identity.role_exceeds_permissions'));
            }
        }

        return Response::allow();
    }

    private function authorizeView(User $actor): Response
    {
        $view = $this->access->authorizePermission($actor, Permission::UsersView);

        return $view->allowed() ? $view : $this->access->authorizePermission($actor, Permission::UsersManage);
    }

    /**
     * @return list<string>
     */
    private static function effectivePermissions(User $user): array
    {
        return array_values($user->getAllPermissions()
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name))
            ->all());
    }

    /**
     * @param  list<string>  $held
     * @param  list<string>  $required
     */
    private static function covers(array $held, array $required): bool
    {
        return array_diff($required, $held) === [];
    }
}
