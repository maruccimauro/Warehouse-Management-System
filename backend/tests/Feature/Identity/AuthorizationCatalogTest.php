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
        ->and(RoleModel::findByName(Role::Auditor->value)->permissions->pluck('name')->all())->toBe(['audit.view']);
});

it('keeps the roles assigned to users when synchronizing', function () {
    app(SyncRolesAndPermissions::class)();
    $user = User::factory()->create();
    $user->assignRole(Role::Supervisor->value);

    app(SyncRolesAndPermissions::class)();

    expect($user->fresh()?->hasRole(Role::Supervisor->value))->toBeTrue();
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
        ->and($roleOf('usuario01@wms.test'))->toBe([Role::Operator->value])
        ->and($roleOf('usuario03@wms.test'))->toBe([Role::Supervisor->value])
        ->and($roleOf('usuario04@wms.test'))->toBe([Role::Purchasing->value])
        ->and($roleOf('usuario05@wms.test'))->toBe([Role::Auditor->value])
        ->and($roleOf('usuario06@wms.test'))->toBe([Role::Operator->value]);

    User::query()->where('email', 'usuario01@wms.test')->sole()->syncRoles([Role::Auditor->value]);
    $this->seed(DatabaseSeeder::class);

    expect($roleOf('usuario01@wms.test'))->toBe([Role::Auditor->value]);
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
