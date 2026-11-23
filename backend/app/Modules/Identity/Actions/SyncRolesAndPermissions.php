<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lleva a la base el catálogo de permisos y roles definido en código (E2.07).
 *
 * No es un dato de desarrollo: corre en todos los entornos (comando
 * wms:sync-authorization) y también desde el seeder de desarrollo. Es
 * idempotente. El código es la fuente de verdad: un permiso o un rol que ya no
 * está en los enums se elimina junto con sus asignaciones, y cada rol queda
 * exactamente con los permisos que declara. Las asignaciones de roles a
 * usuarios no se tocan.
 */
final class SyncRolesAndPermissions
{
    public const GUARD = 'web';

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function __invoke(): void
    {
        DB::transaction(function (): void {
            $this->registrar->forgetCachedPermissions();

            $permissionNames = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());
            $roleNames = array_map(fn (Role $role): string => $role->value, Role::cases());

            PermissionModel::query()->where('guard_name', self::GUARD)->whereNotIn('name', $permissionNames)->delete();
            RoleModel::query()->where('guard_name', self::GUARD)->whereNotIn('name', $roleNames)->delete();

            foreach ($permissionNames as $name) {
                PermissionModel::findOrCreate($name, self::GUARD);
            }

            // spatie limpia su caché con eventos de modelo, que el seeder
            // desactiva (WithoutModelEvents): sin esto, syncPermissions no
            // encuentra los permisos recién creados.
            $this->registrar->forgetCachedPermissions();

            foreach (Role::cases() as $role) {
                RoleModel::findOrCreate($role->value, self::GUARD)->syncPermissions(
                    array_map(fn (Permission $permission): string => $permission->value, $role->permissions()),
                );
            }
        });

        $this->registrar->forgetCachedPermissions();
    }
}
