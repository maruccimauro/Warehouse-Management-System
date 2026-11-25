<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Identity\Actions\CreateUser;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Actions\UpdateUser;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Data\UserChanges;
use App\Modules\Identity\Exceptions\LastAdministrator;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AdministratorContinuity;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\RecordingAuditLogger;

/*
| Gestión de usuarios (E2.09, SU01): alta con contraseña inicial (REG-021),
| roles y depósitos como reemplazo completo (D6), desactivación sin borrado
| con cierre de sesiones (REG-020), continuidad del administrador (REG-022) y
| prevención de la escalada de privilegios (REG-023).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->audit = new RecordingAuditLogger;
    app()->instance(AuditLogger::class, $this->audit);

    $this->admin = userWithAccess(Role::Administrator);
    $this->central = app(CreateWarehouse::class)('CEN', 'Central');
    $this->north = app(CreateWarehouse::class)('NOR', 'Norte');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userPayload(array $overrides = []): array
{
    return [
        'username' => 'AOPERARIA',
        'name' => 'Ana Operaria',
        'email' => 'ana@wms.test',
        'password' => 'clave-inicial-segura',
        'roles' => [Role::Operator->value],
        'warehouse_ids' => [],
        ...$overrides,
    ];
}

/**
 * @param  list<Role>  $roles
 * @param  list<Warehouse>  $warehouses
 */
function managedUser(array $roles = [Role::Operator], array $warehouses = [], string $email = 'gestionado@wms.test'): User
{
    return app(CreateUser::class)(
        [
            'username' => mb_strtoupper(strstr($email, '@', true) ?: $email),
            'name' => 'Usuario Gestionado',
            'email' => $email,
            'password' => 'clave-inicial-segura',
        ],
        $roles,
        array_map(fn (Warehouse $warehouse): int => $warehouse->id, $warehouses),
    );
}

/**
 * Usuario con users.manage asignado directo, sin el rol Administrador, para
 * probar las reglas que hoy el catálogo de roles no permite alcanzar.
 *
 * @param  list<Permission>  $permissions
 */
function userManager(Role $role, array $permissions = []): User
{
    $user = userWithAccess($role);
    $user->givePermissionTo(array_map(
        fn (Permission $permission): string => $permission->value,
        [Permission::UsersManage, ...$permissions],
    ));

    return $user;
}

/**
 * @return list<string>
 */
function auditedActions(RecordingAuditLogger $audit): array
{
    return array_map(fn (array $record): string => $record['action'], $audit->records);
}

describe('creation', function () {
    it('creates an active user with roles, warehouses and an initial password to change', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/users', userPayload([
                'username' => ' aoperaria ',
                'email' => ' Ana@WMS.test ',
                'roles' => [Role::Supervisor->value, Role::Operator->value],
                'warehouse_ids' => [$this->north->id, $this->central->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.username', 'AOPERARIA')
            ->assertJsonPath('data.email', 'ana@wms.test')
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.password_change_required', true)
            ->assertJsonPath('data.roles', ['operator', 'supervisor'])
            ->assertJsonPath('data.warehouses.0.code', 'CEN')
            ->assertJsonPath('data.warehouses.1.code', 'NOR')
            ->assertJsonMissingPath('data.password');

        $user = User::query()->findOrFail($response->json('data.id'));
        expect(Hash::check('clave-inicial-segura', $user->password))->toBeTrue();

        expect($this->audit->records)->toHaveCount(1)
            ->and($this->audit->records[0]['action'])->toBe('user.created')
            ->and($this->audit->records[0]['after'])->toBe([
                'username' => 'AOPERARIA',
                'email' => 'ana@wms.test',
                'roles' => ['operator', 'supervisor'],
                'warehouse_ids' => [$this->central->id, $this->north->id],
            ])
            ->and($this->audit->records[0]['transaction_level'])->toBeGreaterThan(0);
    });

    it('accepts a supported locale and an inactive warehouse', function () {
        $this->north->is_active = false;
        $this->north->save();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', userPayload(['locale' => 'en', 'warehouse_ids' => [$this->north->id]]))
            ->assertCreated()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.warehouses.0.is_active', false);
    });

    it('rejects invalid data', function (array $overrides, string $field) {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', userPayload($overrides))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'contraseña corta' => [['password' => 'corta'], 'password'],
        'email inválido' => [['email' => 'no-es-email'], 'email'],
        'rol inexistente' => [['roles' => ['superuser']], 'roles.0'],
        'rol repetido' => [['roles' => ['operator', 'operator']], 'roles.0'],
        'depósito inexistente' => [['warehouse_ids' => [999999]], 'warehouse_ids.0'],
        'idioma no admitido' => [['locale' => 'fr'], 'locale'],
        'estado en el alta' => [['is_active' => false], 'is_active'],
        'sin roles' => [['roles' => null], 'roles'],
        'sin código de usuario' => [['username' => null], 'username'],
        'código de usuario largo' => [['username' => 'ABCDEFGHIJKLM'], 'username'],
        'código de usuario con símbolos' => [['username' => 'J.PEREZ'], 'username'],
    ]);

    it('rejects a duplicated username regardless of case', function () {
        managedUser(email: 'ana@wms.test');

        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', userPayload(['username' => 'ana', 'email' => 'otra@wms.test']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username'], 'errors');
    });

    it('rejects a duplicated email regardless of case', function () {
        managedUser(email: 'ana@wms.test');

        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', userPayload(['email' => 'ANA@wms.test']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'], 'errors');
    });
});

describe('listing and detail', function () {
    beforeEach(function () {
        managedUser([Role::Operator], [$this->central], 'beto@wms.test');
        $inactive = managedUser([Role::Auditor], [$this->north], 'carla@wms.test');
        app(UpdateUser::class)($this->admin, $inactive, new UserChanges(isActive: false));
    });

    it('lists users with their roles and warehouses', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/users?sort=email')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/users?filter[email]=beto')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'beto@wms.test')
            ->assertJsonPath('data.0.roles', ['operator'])
            ->assertJsonPath('data.0.warehouses.0.code', 'CEN');
    });

    it('filters users', function (string $query, array $emails) {
        $response = $this->actingAs($this->admin)->getJson("/api/v1/users?sort=email&{$query}")->assertOk();

        expect(array_column($response->json('data'), 'email'))->toBe($emails);
    })->with([
        'por email' => ['filter[email]=CARLA', ['carla@wms.test']],
        'inactivos' => ['filter[is_active]=0', ['carla@wms.test']],
        'por nombre con patrón' => ['filter[name][0][sign]=I&filter[name][0][option]=CP&filter[name][0][low]=*gestionado*', ['beto@wms.test', 'carla@wms.test']],
        'por código de usuario' => ['filter[username][0][sign]=I&filter[username][0][option]=EQ&filter[username][0][low]=BETO', ['beto@wms.test']],
        'por rol' => ['filter[role]=operator', ['beto@wms.test']],
    ]);

    it('filters users by assigned warehouse', function () {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/users?filter[warehouse_id]={$this->north->id}")
            ->assertOk();

        expect(array_column($response->json('data'), 'email'))->toBe(['carla@wms.test']);
    });

    it('rejects an unknown role filter', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/users?filter[role]=superuser')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter.role'], 'errors');
    });

    it('shows a user', function () {
        $user = User::query()->where('email', 'beto@wms.test')->firstOrFail();

        $this->actingAs($this->admin)
            ->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'beto@wms.test');
    });

    it('lists the roles with their permissions', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/roles')->assertOk();

        $roles = collect($response->json('data'))->keyBy('name');

        expect($roles->keys()->all())->toBe(array_map(fn (Role $role): string => $role->value, Role::cases()))
            ->and($roles['auditor']['permissions'])->toBe(['audit.view', 'users.view', 'warehouses.view_all']);
    });
});

describe('update', function () {
    it('updates the data without auditing it', function () {
        $user = managedUser();
        $this->audit->records = [];

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", ['name' => 'Nombre Nuevo', 'email' => 'Nuevo@WMS.test', 'locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nombre Nuevo')
            ->assertJsonPath('data.email', 'nuevo@wms.test')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.roles', ['operator']);

        expect($this->audit->records)->toBe([]);
    });

    it('replaces roles and warehouses and audits each change', function () {
        $user = managedUser([Role::Operator], [$this->central]);
        $this->audit->records = [];

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", [
                'roles' => [Role::Supervisor->value],
                'warehouse_ids' => [$this->north->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.roles', ['supervisor'])
            ->assertJsonCount(1, 'data.warehouses')
            ->assertJsonPath('data.warehouses.0.code', 'NOR');

        expect(auditedActions($this->audit))->toBe(['user.roles_changed', 'user.warehouses_changed'])
            ->and($this->audit->records[0]['before'])->toBe(['roles' => ['operator']])
            ->and($this->audit->records[0]['after'])->toBe(['roles' => ['supervisor']])
            ->and($this->audit->records[1]['before'])->toBe(['warehouse_ids' => [$this->central->id]])
            ->and($this->audit->records[1]['after'])->toBe(['warehouse_ids' => [$this->north->id]]);
    });

    it('does not audit an assignment sent unchanged', function () {
        $user = managedUser([Role::Operator], [$this->central]);
        $this->audit->records = [];

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", ['roles' => ['operator'], 'warehouse_ids' => [$this->central->id]])
            ->assertOk();

        expect($this->audit->records)->toBe([]);
    });

    it('applies a role change on the next request without closing the session', function () {
        $user = managedUser([Role::Auditor]);
        $user->password_change_required = false;
        $user->save();

        app(UpdateUser::class)($this->admin, $user, new UserChanges(roles: [Role::Purchasing]));

        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh() ?? $user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['purchasing']);
    });

    it('resets the password as an initial one and audits it without the value', function () {
        $user = managedUser();
        $user->password_change_required = false;
        $user->save();
        $this->audit->records = [];

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", ['password' => 'otra-clave-inicial'])
            ->assertOk()
            ->assertJsonPath('data.password_change_required', true);

        expect(Hash::check('otra-clave-inicial', (string) $user->fresh()?->password))->toBeTrue()
            ->and(auditedActions($this->audit))->toBe(['user.password_reset'])
            ->and($this->audit->records[0]['before'])->toBeNull()
            ->and($this->audit->records[0]['after'])->toBeNull();
    });

    it('deactivates and reactivates a user, auditing both', function () {
        $user = managedUser();
        $this->audit->records = [];

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        expect(auditedActions($this->audit))->toBe(['user.deactivated', 'user.activated'])
            ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
    });

    it('rejects a duplicated email', function () {
        managedUser(email: 'ocupado@wms.test');
        $user = managedUser();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", ['email' => 'ocupado@wms.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'], 'errors');
    });

    it('does not change the username after creation', function () {
        $user = managedUser();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$user->id}", ['username' => 'OTRO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username'], 'errors');
    });

    it('responds 404 for a non numeric id', function () {
        $this->actingAs($this->admin)->getJson('/api/v1/users/abc')->assertNotFound();
    });
});

describe('sessions', function () {
    beforeEach(function () {
        config(['session.driver' => 'database']);

        $this->user = managedUser();
        $this->other = managedUser(email: 'otro@wms.test');

        foreach ([['s-user-1', $this->user], ['s-user-2', $this->user], ['s-other', $this->other]] as [$id, $owner]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
        }
    });

    it('closes the open sessions of a deactivated user', function () {
        app(UpdateUser::class)($this->admin, $this->user, new UserChanges(isActive: false));

        expect(DB::table('sessions')->pluck('id')->all())->toBe(['s-other']);
    });

    it('closes the open sessions of a user whose password is reset', function () {
        app(UpdateUser::class)($this->admin, $this->user, new UserChanges(password: 'clave-reseteada-segura'));

        expect(DB::table('sessions')->pluck('id')->all())->toBe(['s-other']);
    });

    it('keeps the sessions when only the data or the roles change', function () {
        app(UpdateUser::class)($this->admin, $this->user, new UserChanges(attributes: ['name' => 'Otro'], roles: [Role::Auditor]));

        expect(DB::table('sessions')->count())->toBe(3);
    });
});

describe('administrator continuity (REG-022)', function () {
    it('does not let users deactivate themselves', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->admin->id}", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SELF_DEACTIVATION_NOT_ALLOWED');

        expect($this->admin->fresh()?->is_active)->toBeTrue();
    });

    it('rejects removing the administrator role from the last active administrator', function () {
        $manager = userManager(Role::Supervisor, Permission::cases());

        $this->actingAs($manager)
            ->patchJson("/api/v1/users/{$this->admin->id}", ['roles' => [Role::Supervisor->value]])
            ->assertStatus(409)
            ->assertJsonPath('code', 'LAST_ADMINISTRATOR');

        // La transacción se revierte entera, roles y auditoría incluidos (el
        // registro en memoria de la prueba no participa de la transacción).
        expect($this->admin->fresh()?->hasRole(Role::Administrator->value))->toBeTrue();
    });

    it('rejects deactivating the last active administrator', function () {
        $manager = userManager(Role::Supervisor, Permission::cases());

        $this->actingAs($manager)
            ->patchJson("/api/v1/users/{$this->admin->id}", ['is_active' => false])
            ->assertStatus(409)
            ->assertJsonPath('code', 'LAST_ADMINISTRATOR');

        expect($this->admin->fresh()?->is_active)->toBeTrue();
    });

    it('does not count inactive administrators', function () {
        $inactiveAdmin = managedUser([Role::Administrator], email: 'admin2@wms.test');
        app(UpdateUser::class)($this->admin, $inactiveAdmin, new UserChanges(isActive: false));

        expect(fn () => app(UpdateUser::class)(userManager(Role::Supervisor, Permission::cases()), $this->admin, new UserChanges(isActive: false)))
            ->toThrow(LastAdministrator::class);
    });

    it('allows removing the role while another active administrator remains', function () {
        $other = managedUser([Role::Administrator], email: 'admin2@wms.test');

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$other->id}", ['roles' => [Role::Supervisor->value]])
            ->assertOk()
            ->assertJsonPath('data.roles', ['supervisor']);
    });

    it('takes the advisory lock before changing roles or deactivating', function () {
        DB::transaction(function () {
            app(AdministratorContinuity::class)->lock();

            $held = DB::scalar(
                "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND objid = ? AND pid = pg_backend_pid() AND granted",
                [AdministratorContinuity::ADVISORY_LOCK_KEY],
            );

            expect($held)->toBe(1);
        });
    });
});

describe('privilege escalation (REG-023)', function () {
    it('does not let users change their own roles', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->admin->id}", ['roles' => [Role::Administrator->value, Role::Auditor->value]])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    });

    it('accepts their own roles sent unchanged', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->admin->id}", ['name' => 'Admin', 'roles' => [Role::Administrator->value]])
            ->assertOk();
    });

    it('only assigns roles whose permissions the acting user holds', function () {
        // users.view es del Auditor: sin él, quien opera no podría asignar ese rol.
        $manager = userManager(Role::Supervisor, [Permission::UsersView]);

        $this->actingAs($manager)
            ->postJson('/api/v1/users', userPayload(['roles' => [Role::Administrator->value]]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson('/api/v1/users', userPayload(['roles' => [Role::Auditor->value]]))
            ->assertCreated();
    });

    it('only removes roles whose permissions the acting user holds', function () {
        $buyer = managedUser([Role::Purchasing, Role::Auditor]);

        $this->actingAs(userManager(Role::Supervisor, [Permission::UsersView]))
            ->patchJson("/api/v1/users/{$buyer->id}", ['roles' => [Role::Auditor->value]])
            ->assertForbidden();
    });

    it('does not let a user manage someone with more permissions', function () {
        $this->actingAs(userManager(Role::Supervisor))
            ->patchJson("/api/v1/users/{$this->admin->id}", ['password' => 'tomar-la-cuenta-1'])
            ->assertForbidden();

        expect(Hash::check('tomar-la-cuenta-1', (string) $this->admin->fresh()?->password))->toBeFalse();
    });
});

describe('authorization', function () {
    it('lets users.view consult users and roles but not change them', function (string $method, string $uri, int $status) {
        $target = managedUser();
        $uri = str_replace('{id}', (string) $target->id, $uri);

        $this->actingAs(userWithAccess(Role::Auditor))
            ->json($method, $uri, $method === 'GET' ? [] : userPayload(['email' => 'nuevo@wms.test', 'username' => 'NUEVO']))
            ->assertStatus($status);
    })->with([
        'listado' => ['GET', '/api/v1/users', 200],
        'detalle' => ['GET', '/api/v1/users/{id}', 200],
        'roles' => ['GET', '/api/v1/roles', 200],
        'alta' => ['POST', '/api/v1/users', 403],
        'edición' => ['PATCH', '/api/v1/users/{id}', 403],
    ]);

    it('requires users.view or users.manage on every endpoint', function (string $method, string $uri) {
        $target = managedUser();
        $uri = str_replace('{id}', (string) $target->id, $uri);

        $this->actingAs(userWithAccess(Role::Supervisor))
            ->json($method, $uri, $method === 'GET' ? [] : userPayload(['email' => 'nuevo@wms.test']))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    })->with([
        'listado' => ['GET', '/api/v1/users'],
        'detalle' => ['GET', '/api/v1/users/{id}'],
        'alta' => ['POST', '/api/v1/users'],
        'edición' => ['PATCH', '/api/v1/users/{id}'],
        'roles' => ['GET', '/api/v1/roles'],
    ]);

    it('requires a session', function () {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    });
});
