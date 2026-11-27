<?php

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/*
| Catálogo de roles y permisos (E2.07): el código es la fuente de verdad y
| la sincronización lo lleva a la base en cualquier entorno.
*/

/**
 * @return list<string>
 */
function permissionNamesOf(Role $role): array
{
    $names = array_map(fn (Permission $permission): string => $permission->value, $role->permissions());
    sort($names);

    return $names;
}

it('names every permission as module.action', function (Permission $permission) {
    expect($permission->value)->toMatch('/^[a-z_]+(\.[a-z_]+)+$/');
})->with(Permission::cases());

it('does not repeat permissions within a role', function (Role $role) {
    expect($role->permissions())->toHaveCount(count(array_unique(permissionNamesOf($role))));
})->with(Role::cases());

it('creates every permission and role of the catalog', function () {
    app(SyncRolesAndPermissions::class)();

    expect(PermissionModel::query()->orderBy('name')->pluck('name')->all())
        ->toEqualCanonicalizing(array_map(fn (Permission $permission): string => $permission->value, Permission::cases()))
        ->and(RoleModel::query()->pluck('name')->all())
        ->toEqualCanonicalizing(array_map(fn (Role $role): string => $role->value, Role::cases()));
});

it('gives each role exactly the permissions it declares', function (Role $role) {
    app(SyncRolesAndPermissions::class)();

    expect(RoleModel::findByName($role->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(permissionNamesOf($role));
})->with(Role::cases());

it('gives the administrator every permission', function () {
    expect(permissionNamesOf(Role::Administrator))->toHaveCount(count(Permission::cases()));
});

it('is idempotent and removes what left the catalog', function () {
    app(SyncRolesAndPermissions::class)();
    PermissionModel::findOrCreate('obsolete.permission', SyncRolesAndPermissions::GUARD);
    RoleModel::findOrCreate('obsolete-role', SyncRolesAndPermissions::GUARD)->givePermissionTo('audit.view');
    RoleModel::findByName(Role::Auditor->value)->givePermissionTo('users.manage');

    app(SyncRolesAndPermissions::class)();
    app(SyncRolesAndPermissions::class)();

    expect(PermissionModel::query()->count())->toBe(count(Permission::cases()))
        ->and(RoleModel::query()->where('name', 'obsolete-role')->exists())->toBeFalse()
        ->and(RoleModel::findByName(Role::Auditor->value)->permissions->pluck('name')->sort()->values()->all())->toBe(permissionNamesOf(Role::Auditor));
});

it('keeps the roles assigned to users when synchronizing', function () {
    app(SyncRolesAndPermissions::class)();
    $user = User::factory()->create();
    $user->assignRole(Role::Supervisor->value);

    app(SyncRolesAndPermissions::class)();

    expect($user->fresh()?->hasRole(Role::Supervisor->value))->toBeTrue();
});

it('distinguishes the operations manager from the lead only by the scope over every warehouse', function () {
    $manager = array_values(array_diff(permissionNamesOf(Role::OperationsManager), [Permission::WarehousesOperateAll->value]));

    expect(permissionNamesOf(Role::OperationsManager))->toContain(Permission::WarehousesOperateAll->value)
        ->and($manager)->toBe(permissionNamesOf(Role::OperationsLead));
});

it('keeps the assignments of the former operations manager in the operations lead when migrating', function () {
    RoleModel::query()->where('name', Role::OperationsLead->value)->delete();
    RoleModel::query()->where('name', Role::OperationsManager->value)->delete();
    $former = RoleModel::findOrCreate('operations_manager', SyncRolesAndPermissions::GUARD);
    $user = User::factory()->create();
    $user->assignRole($former);

    $migration = require database_path('../app/Modules/Identity/Database/Migrations/2026_11_26_100000_rename_operations_manager_to_operations_lead.php');
    $migration->up();
    app(SyncRolesAndPermissions::class)();

    $user = User::query()->findOrFail($user->id);
    expect($user->hasRole(Role::OperationsLead->value))->toBeTrue()
        ->and($user->hasRole(Role::OperationsManager->value))->toBeFalse()
        ->and(RoleModel::query()->where('name', Role::OperationsManager->value)->exists())->toBeTrue();
});

it('stores the morph alias instead of the class name', function () {
    app(SyncRolesAndPermissions::class)();
    User::factory()->create()->assignRole(Role::Operator->value);

    expect(DB::table('model_has_roles')->value('model_type'))->toBe('user');
});

it('synchronizes from the console command', function () {
    $this->artisan('wms:sync-authorization')->assertSuccessful();

    expect(RoleModel::query()->count())->toBe(count(Role::cases()));
});

it('assigns a role to every development user without overwriting manual changes', function () {
    config()->set('wms.demo_users', 6);
    $this->seed(DatabaseSeeder::class);

    $roleOf = fn (string $email): array => User::query()->where('email', $email)->sole()->getRoleNames()->all();

    expect($roleOf('admin@wms.test'))->toBe([Role::Administrator->value])
        ->and($roleOf('maria.martinez@wms.test'))->toBe([Role::Operator->value])
        ->and($roleOf('lucia.fernandez@wms.test'))->toBe([Role::Supervisor->value])
        ->and($roleOf('martin.gomez@wms.test'))->toBe([Role::Purchasing->value])
        ->and($roleOf('sofia.rodriguez@wms.test'))->toBe([Role::Auditor->value])
        ->and($roleOf('diego.lopez@wms.test'))->toBe([Role::Operator->value]);

    User::query()->where('email', 'maria.martinez@wms.test')->sole()->syncRoles([Role::Auditor->value]);
    $this->seed(DatabaseSeeder::class);

    expect($roleOf('maria.martinez@wms.test'))->toBe([Role::Auditor->value]);
});

it('returns the roles and effective permissions of the authenticated user', function () {
    app(SyncRolesAndPermissions::class)();
    $user = User::factory()->create();
    $user->assignRole(Role::Purchasing->value);

    $this->actingAs($user)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.roles', [Role::Purchasing->value])
        ->assertJsonPath('data.permissions', permissionNamesOf(Role::Purchasing));
});
