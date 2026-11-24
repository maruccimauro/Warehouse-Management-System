<?php

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\SystemLocationRole;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
| Alcance por depósito (E2.08, REG-005, ADR-009). Dos niveles, como la
| actividad de los objetos de autorización de SAP sobre WERKS: consulta
| (warehouses.view_all o asignación) y operación (warehouses.operate_all o
| asignación). Fuera del alcance de consulta, 404; visible pero sin operación
| o sin permiso de la acción, 403.
|
| La ruta de prueba de consulta de depósito ejercita la Policy por HTTP hasta
| que exista el ABM de depósitos; las operaciones usan el bloqueo real de
| ubicaciones (E2.04).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->norte = app(CreateWarehouse::class)('NORTE', 'Depósito norte');

    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/__tests')->group(function () {
        Route::get('/warehouses/{warehouse}', function (Warehouse $warehouse) {
            Gate::authorize('view', $warehouse);

            return ['code' => $warehouse->code];
        });
    });
});

function receivingLocationOf(Warehouse $warehouse): Location
{
    return $warehouse->locations()->where('system_role', SystemLocationRole::Receiving)->sole();
}

/**
 * @param  class-string<Warehouse|Zone|Location>  $model
 * @return list<string>
 */
function visibleWarehouseCodes(string $model, User $user): array
{
    $warehouseIds = $model::query()->visibleTo($user)->pluck($model === Warehouse::class ? 'id' : 'warehouse_id');

    return Warehouse::query()->whereIn('id', $warehouseIds)->orderBy('code')->pluck('code')->all();
}

it('restricts queries to the assigned warehouses without warehouses.view_all', function (string $model) {
    $user = userWithAccess(null, [$this->central]);

    expect(visibleWarehouseCodes($model, $user))->toBe(['CENTRAL']);
})->with([Warehouse::class, Zone::class, Location::class]);

it('shows every warehouse to roles with warehouses.view_all', function (Role $role, string $model) {
    $user = userWithAccess($role, [$this->central]);

    expect(visibleWarehouseCodes($model, $user))->toBe(['CENTRAL', 'NORTE']);
})->with([Role::Operator, Role::Supervisor, Role::Purchasing, Role::Auditor, Role::Administrator])
    ->with([Warehouse::class, Zone::class, Location::class]);

it('lets users operate only in their assigned warehouses', function () {
    $operator = userWithAccess(Role::Operator, [$this->central]);

    expect(Warehouse::query()->operableBy($operator)->pluck('code')->all())->toBe(['CENTRAL'])
        ->and(Location::query()->operableBy($operator)->pluck('warehouse_id')->unique()->values()->all())->toBe([$this->central->id]);
});

it('lets the administrator operate in every warehouse without assignments', function () {
    $admin = userWithAccess(Role::Administrator);

    expect(Warehouse::query()->operableBy($admin)->orderBy('code')->pluck('code')->all())->toBe(['CENTRAL', 'NORTE']);
});

it('answers 404 for a warehouse outside the viewing scope', function () {
    $this->actingAs(userWithAccess(null, [$this->central]))
        ->getJson("/api/v1/__tests/warehouses/{$this->norte->id}")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonPath('detail', __('problems.http.404'));
});

it('answers 404 to an operation outside the viewing scope', function () {
    $this->actingAs(userWithAccess(null, [$this->central]))
        ->postJson('/api/v1/locations/'.receivingLocationOf($this->norte)->id.'/block', ['reason' => 'Muelle fuera de servicio'])
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('shows a warehouse that is visible but not assigned', function () {
    $this->actingAs(userWithAccess(Role::Operator, [$this->central]))
        ->getJson("/api/v1/__tests/warehouses/{$this->norte->id}")
        ->assertOk()
        ->assertJsonPath('code', 'NORTE');
});

it('answers 403 to an operation in a visible warehouse that is not assigned', function () {
    $this->actingAs(userWithAccess(Role::Supervisor, [$this->central]))
        ->postJson('/api/v1/locations/'.receivingLocationOf($this->norte)->id.'/block', ['reason' => 'Muelle fuera de servicio'])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath('detail', __('identity.warehouse_not_assigned'));
});

it('answers 403 to an operation in an assigned warehouse without the permission of the action', function () {
    $this->actingAs(userWithAccess(Role::Operator, [$this->central]))
        ->postJson('/api/v1/locations/'.receivingLocationOf($this->central)->id.'/block', ['reason' => 'Muelle fuera de servicio'])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath('detail', __('identity.permission_denied'));
});

it('allows an operation in an assigned warehouse with the permission of the action', function () {
    $this->actingAs(userWithAccess(Role::Supervisor, [$this->central]))
        ->postJson('/api/v1/locations/'.receivingLocationOf($this->central)->id.'/block', ['reason' => 'Muelle fuera de servicio'])
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');
});

it('allows the administrator to operate in any warehouse', function () {
    $this->actingAs(userWithAccess(Role::Administrator))
        ->postJson('/api/v1/locations/'.receivingLocationOf($this->norte)->id.'/block', ['reason' => 'Muelle fuera de servicio'])
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');
});

it('requires warehouses.manage to create or edit a warehouse', function () {
    $supervisor = userWithAccess(Role::Supervisor, [$this->central]);
    $admin = userWithAccess(Role::Administrator);

    expect(Gate::forUser($supervisor)->allows('create', Warehouse::class))->toBeFalse()
        ->and(Gate::forUser($supervisor)->allows('update', $this->central))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('create', Warehouse::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $this->norte))->toBeTrue();
});

it('requires locations.manage in an operable warehouse to create or edit zones and locations', function () {
    $admin = userWithAccess(Role::Administrator);
    $supervisor = userWithAccess(Role::Supervisor, [$this->central]);
    $zone = $this->central->zones()->firstOrFail();

    expect(Gate::forUser($admin)->allows('create', [Zone::class, $this->norte]))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', receivingLocationOf($this->norte)))->toBeTrue()
        ->and(Gate::forUser($supervisor)->allows('create', [Location::class, $this->central]))->toBeFalse()
        ->and(Gate::forUser($supervisor)->allows('update', $zone))->toBeFalse();
});

it('lists the active warehouses where the user operates in GET /auth/me', function () {
    $inactive = app(CreateWarehouse::class)('SUR', 'Depósito sur');
    $inactive->update(['is_active' => false]);
    $operator = userWithAccess(Role::Operator, [$this->norte, $inactive, $this->central]);

    $this->actingAs($operator)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.warehouses', [
            ['id' => $this->central->id, 'code' => 'CENTRAL', 'name' => 'Depósito central'],
            ['id' => $this->norte->id, 'code' => 'NORTE', 'name' => 'Depósito norte'],
        ]);
});

it('does not list visible warehouses that are not assigned in GET /auth/me', function () {
    $this->actingAs(userWithAccess(Role::Operator, [$this->central]))
        ->getJson('/api/v1/auth/me')
        ->assertJsonPath('data.warehouses.*.code', ['CENTRAL']);
});

it('lists every active warehouse to the administrator in GET /auth/me', function () {
    $this->actingAs(userWithAccess(Role::Administrator))
        ->getJson('/api/v1/auth/me')
        ->assertJsonPath('data.warehouses.*.code', ['CENTRAL', 'NORTE']);
});
