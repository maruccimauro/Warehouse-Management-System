<?php

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Enums\SystemLocationRole;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;

/*
| Zonas y ubicaciones (E2.03): ABM con alcance por depósito (REG-005) y
| generación masiva por plantilla, todo o nada.
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->norte = app(CreateWarehouse::class)('NORTE', 'Depósito norte');
    $this->storage = $this->central->zones()->create(['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => ZoneType::Storage]);
    $this->admin = userWithAccess(Role::Administrator);
});

/**
 * Operador asignado a los depósitos dados, con locations.manage directo: el
 * caso de un usuario que administra ubicaciones solo donde opera.
 *
 * @param  list<Warehouse>  $warehouses
 */
function locationManager(array $warehouses): User
{
    $user = userWithAccess(Role::Operator, $warehouses);
    $user->givePermissionTo(Permission::LocationsManage->value);

    return $user;
}

function systemZoneOf(Warehouse $warehouse): Zone
{
    return $warehouse->zones()->where('is_system', true)->where('code', SystemLocationRole::Receiving->code())->sole();
}

function systemLocationOf(Warehouse $warehouse): Location
{
    return $warehouse->locations()->where('system_role', SystemLocationRole::Receiving)->sole();
}

/**
 * Plantilla del plan de E2: pasillos A a D, estanterías 1 a 10, niveles 1 a 4.
 *
 * @return array<string, mixed>
 */
function gridPayload(Zone $zone, array $overrides = []): array
{
    return [
        'zone_id' => $zone->id,
        'aisles' => ['from' => 'A', 'to' => 'D'],
        'racks' => ['from' => 1, 'to' => 10],
        'levels' => ['from' => 1, 'to' => 4],
        ...$overrides,
    ];
}

/*
| Cada endpoint de escritura: método, URI y cuerpo válido, sobre CENTRAL.
*/
dataset('write endpoints', [
    'alta de zona' => [fn () => ['post', "/api/v1/warehouses/{$this->central->id}/zones", ['code' => 'PICK', 'name' => 'Picking', 'type' => 'picking']]],
    'modificación de zona' => [fn () => ['patch', "/api/v1/zones/{$this->storage->id}", ['name' => 'Almacén alto']]],
    'alta de ubicación' => [fn () => ['post', "/api/v1/warehouses/{$this->central->id}/locations", ['code' => 'Z-01', 'zone_id' => $this->storage->id]]],
    'generación masiva' => [fn () => ['post', "/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage)]],
    'modificación de ubicación' => [fn () => ['patch', '/api/v1/locations/'.$this->central->locations()->create(['zone_id' => $this->storage->id, 'code' => 'Y-01'])->id, ['max_units' => 40]]],
]);

describe('authorization matrix', function () {
    it('allows the administrator, who operates every warehouse', function (Closure $endpoint) {
        [$method, $uri, $body] = $endpoint->call($this);

        $this->actingAs($this->admin)->json($method, $uri, $body)->assertSuccessful();
    })->with('write endpoints');

    it('allows a user with locations.manage in an assigned warehouse', function (Closure $endpoint) {
        [$method, $uri, $body] = $endpoint->call($this);

        $this->actingAs(locationManager([$this->central]))->json($method, $uri, $body)->assertSuccessful();
    })->with('write endpoints');

    it('denies with 403 a role without locations.manage', function (Closure $endpoint, Role $role) {
        [$method, $uri, $body] = $endpoint->call($this);

        $this->actingAs(userWithAccess($role, [$this->central]))->json($method, $uri, $body)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN')
            ->assertJsonPath('detail', __('identity.permission_denied'));
    })->with('write endpoints')->with([Role::Supervisor, Role::Operator, Role::Purchasing, Role::Auditor]);

    it('denies with 403 in a visible warehouse that is not assigned', function (Closure $endpoint) {
        [$method, $uri, $body] = $endpoint->call($this);

        $this->actingAs(locationManager([$this->norte]))->json($method, $uri, $body)
            ->assertForbidden()
            ->assertJsonPath('detail', __('identity.warehouse_not_assigned'));
    })->with('write endpoints');

    it('responds 404 outside the query scope, before validating the body', function (Closure $endpoint) {
        [$method, $uri] = $endpoint->call($this);
        $user = userWithAccess(null, [$this->norte]);
        $user->givePermissionTo(Permission::LocationsManage->value);

        $this->actingAs($user)->json($method, $uri, ['invalid' => true])
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    })->with('write endpoints');

    it('requires a session', function (Closure $endpoint) {
        [$method, $uri, $body] = $endpoint->call($this);

        $this->json($method, $uri, $body)->assertUnauthorized();
    })->with('write endpoints');
});

describe('queries', function () {
    it('lists the zones of a visible warehouse, paginated and sorted by code', function () {
        $response = $this->actingAs(userWithAccess(Role::Auditor))
            ->getJson("/api/v1/warehouses/{$this->central->id}/zones")
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        expect(array_column($response->json('data'), 'code'))->toBe(['ALM', 'DESPACHO', 'PACKING', 'RECEPCION'])
            ->and($response->json('data.0'))->toMatchArray(['warehouse_id' => $this->central->id, 'type' => 'storage', 'is_system' => false]);
    });

    it('filters the zones by type and system flag', function () {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/warehouses/{$this->central->id}/zones?filter[is_system]=0")
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/warehouses/{$this->central->id}/zones?filter[type][]=receiving&filter[type][]=packing")
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/warehouses/{$this->central->id}/zones?filter[type][]=garage")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter.type.0'], 'errors');
    });

    it('lists the locations of a warehouse filtered by zone and a code pattern', function () {
        $this->actingAs($this->admin)->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage))->assertCreated();

        // El código es un criterio de selección: CP con * busca por patrón y sin
        // distinguir mayúsculas, como el fragmento de la ayuda de búsqueda.
        $this->actingAs(userWithAccess(Role::Operator))
            ->getJson("/api/v1/warehouses/{$this->central->id}/locations?filter[zone_id][]={$this->storage->id}&filter[code][0][option]=CP&filter[code][0][low]=*b-1*&per_page=100")
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.code', 'B-10-01')
            ->assertJsonPath('data.0.aisle', 'B')
            ->assertJsonPath('data.0.rack', '10')
            ->assertJsonPath('data.0.status', 'active');
    });

    it('lists the locations of a warehouse in a code range', function () {
        $this->actingAs($this->admin)->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage))->assertCreated();

        $this->actingAs(userWithAccess(Role::Operator))
            ->getJson("/api/v1/warehouses/{$this->central->id}/locations?filter[code][0][option]=BT&filter[code][0][low]=A-01-01&filter[code][0][high]=A-01-99&per_page=100")
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.code', 'A-01-01')
            ->assertJsonPath('data.3.code', 'A-01-04');
    });

    it('shows a zone and a location of a visible warehouse', function () {
        $user = userWithAccess(Role::Auditor);

        $this->actingAs($user)->getJson("/api/v1/zones/{$this->storage->id}")->assertOk()->assertJsonPath('data.code', 'ALM');
        $this->actingAs($user)->getJson('/api/v1/locations/'.systemLocationOf($this->central)->id)
            ->assertOk()
            ->assertJsonPath('data.system_role', 'receiving')
            ->assertJsonPath('data.is_system', true);
    });

    it('responds 404 for the zones and locations of a warehouse outside the query scope', function (string $uri) {
        $uri = strtr($uri, [
            '{warehouse}' => (string) $this->central->id,
            '{zone}' => (string) $this->storage->id,
            '{location}' => (string) systemLocationOf($this->central)->id,
        ]);

        $this->actingAs(userWithAccess(null, [$this->norte]))->getJson($uri.'?sort=invalid')->assertNotFound();
    })->with([
        'listado de zonas' => '/api/v1/warehouses/{warehouse}/zones',
        'listado de ubicaciones' => '/api/v1/warehouses/{warehouse}/locations',
        'zona' => '/api/v1/zones/{zone}',
        'ubicación' => '/api/v1/locations/{location}',
    ]);

    it('responds 404 for an identifier that is not a valid BIGINT', function (string $uri) {
        $this->actingAs($this->admin)->getJson($uri)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    })->with(['/api/v1/zones/abc', '/api/v1/locations/99999999999999999999', '/api/v1/warehouses/0/zones']);
});

describe('zones', function () {
    it('creates a user zone with the code in uppercase', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/zones", ['code' => ' pick-1 ', 'name' => 'Picking 1', 'type' => 'picking'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PICK-1')
            ->assertJsonPath('data.is_system', false);
    });

    it('rejects a zone code repeated in the warehouse but admits it in another one', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/zones", ['code' => 'alm', 'name' => 'Otra', 'type' => 'storage'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'], 'errors');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->norte->id}/zones", ['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => 'storage'])
            ->assertCreated();
    });

    it('rejects an invalid code or type', function (array $body, string $field) {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/zones", [...['code' => 'Z1', 'name' => 'Zona', 'type' => 'storage'], ...$body])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'código con espacios' => [['code' => 'Z 1'], 'code'],
        'código con separador final' => [['code' => 'Z1-'], 'code'],
        'código demasiado largo' => [['code' => str_repeat('Z', 21)], 'code'],
        'tipo inexistente' => [['type' => 'garage'], 'type'],
    ]);

    it('updates the name and type of a user zone, never its code', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/zones/{$this->storage->id}", ['name' => 'Cuarentena', 'type' => 'quarantine'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cuarentena')
            ->assertJsonPath('data.type', 'quarantine');

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/zones/{$this->storage->id}", ['code' => 'OTRO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'], 'errors');
    });

    it('keeps the type of a system zone but admits renaming it', function () {
        $zone = systemZoneOf($this->central);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/zones/{$zone->id}", ['type' => 'storage'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type'], 'errors');

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/zones/{$zone->id}", ['name' => 'Muelle de recepción'])
            ->assertOk()
            ->assertJsonPath('data.type', 'receiving');
    });
});

describe('locations', function () {
    it('creates a location with coordinates and capacity', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations", [
                'code' => 'a-01-02', 'zone_id' => $this->storage->id, 'aisle' => 'a', 'rack' => '01', 'level' => '02', 'max_units' => 120,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'A-01-02')
            ->assertJsonPath('data.aisle', 'A')
            ->assertJsonPath('data.max_units', 120)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_system', false);
    });

    it('rejects a location in a system zone or in a zone of another warehouse', function (Closure $zone) {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations", ['code' => 'X-01', 'zone_id' => $zone->call($this)->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['zone_id'], 'errors');
    })->with([
        'zona de sistema' => [fn () => systemZoneOf($this->central)],
        'zona de otro depósito' => [fn () => $this->norte->zones()->create(['code' => 'ALM', 'name' => 'Almacén', 'type' => ZoneType::Storage])],
    ]);

    it('rejects a location code repeated in the warehouse', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations", ['code' => 'recepcion', 'zone_id' => $this->storage->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'], 'errors');
    });

    it('rejects a capacity that is not a positive integer', function (mixed $maxUnits) {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations", ['code' => 'X-01', 'zone_id' => $this->storage->id, 'max_units' => $maxUnits])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_units'], 'errors');
    })->with([0, -5, 1.5, 'mucho']);

    it('updates the zone, coordinates and capacity, and clears the capacity with null', function () {
        $location = $this->central->locations()->create(['zone_id' => $this->storage->id, 'code' => 'Y-01', 'max_units' => 10]);
        $other = $this->central->zones()->create(['code' => 'ALM2', 'name' => 'Almacén 2', 'type' => ZoneType::Storage]);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/locations/{$location->id}", ['zone_id' => $other->id, 'level' => 'b', 'max_units' => null])
            ->assertOk()
            ->assertJsonPath('data.zone_id', $other->id)
            ->assertJsonPath('data.level', 'B')
            ->assertJsonPath('data.max_units', null)
            ->assertJsonPath('data.code', 'Y-01');
    });

    it('never changes the code, nor blocks or counts through the update', function (string $field, string $value) {
        $location = $this->central->locations()->create(['zone_id' => $this->storage->id, 'code' => 'Y-01']);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/locations/{$location->id}", [$field => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([['code', 'Y-02'], ['status', 'blocked'], ['status', 'counting']]);

    it('deactivates and reactivates a user location', function () {
        $location = $this->central->locations()->create(['zone_id' => $this->storage->id, 'code' => 'Y-01']);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/locations/{$location->id}", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/locations/{$location->id}", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    });

    it('rejects with 409 deactivating a blocked or counting location', function (LocationStatus $current) {
        $location = $this->central->locations()->create([
            'zone_id' => $this->storage->id, 'code' => 'Y-01', 'status' => $current, 'status_reason' => 'Rack dañado',
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/locations/{$location->id}", ['status' => 'inactive'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATE_TRANSITION')
            ->assertJsonPath('context', ['from' => $current->value, 'to' => 'inactive']);

        expect($location->refresh()->status)->toBe($current);
    })->with([LocationStatus::Blocked, LocationStatus::Counting]);

    it('keeps a system location in its zone and never deactivates it', function (array $body, string $field) {
        $this->actingAs($this->admin)
            ->patchJson('/api/v1/locations/'.systemLocationOf($this->central)->id, $body)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'cambio de zona' => [fn () => ['zone_id' => $this->storage->id], 'zone_id'],
        'desactivación' => [['status' => 'inactive'], 'status'],
    ]);
});

describe('bulk generation', function () {
    it('generates aisles A to D, racks 1 to 10 and levels 1 to 4 in one batch', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage, ['max_units' => 50]))
            ->assertCreated()
            ->assertExactJson(['data' => ['created' => 160]]);

        $locations = $this->storage->locations()->orderBy('code')->get();

        expect($locations)->toHaveCount(160)
            ->and($locations->first()?->only(['code', 'aisle', 'rack', 'level', 'position', 'max_units', 'warehouse_id']))
            ->toBe(['code' => 'A-01-01', 'aisle' => 'A', 'rack' => '01', 'level' => '01', 'position' => null, 'max_units' => 50, 'warehouse_id' => $this->central->id])
            ->and($locations->last()?->code)->toBe('D-10-04');
    });

    it('adds the position to the code when the template has positions', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage, [
                'aisles' => ['from' => 'c', 'to' => 'c'], 'racks' => ['from' => 1, 'to' => 1], 'levels' => ['from' => 2, 'to' => 2], 'positions' => ['from' => 1, 'to' => 3],
            ]))
            ->assertExactJson(['data' => ['created' => 3]]);

        expect($this->storage->locations()->orderBy('code')->pluck('code')->all())->toBe(['C-01-02-01', 'C-01-02-02', 'C-01-02-03']);
    });

    it('reverts the whole batch when a single code already exists', function () {
        $this->central->locations()->create(['zone_id' => $this->storage->id, 'code' => 'C-05-03']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALUE_ALREADY_EXISTS')
            ->assertJsonPath('context', ['field' => 'code', 'values' => ['C-05-03']]);

        expect($this->storage->locations()->count())->toBe(1);
    });

    it('admits the same template in another warehouse', function () {
        $northStorage = $this->norte->zones()->create(['code' => 'ALM', 'name' => 'Almacén', 'type' => ZoneType::Storage]);

        $this->actingAs($this->admin)->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage))->assertCreated();
        $this->actingAs($this->admin)->postJson("/api/v1/warehouses/{$this->norte->id}/locations/bulk", gridPayload($northStorage))->assertCreated();

        expect(Location::query()->where('is_system', false)->count())->toBe(320);
    });

    it('rejects an invalid template', function (array $overrides, string $field) {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload($this->storage, $overrides))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'pasillos invertidos' => [['aisles' => ['from' => 'D', 'to' => 'A']], 'aisles.to'],
        'pasillo que no es una letra' => [['aisles' => ['from' => 'AA', 'to' => 'B']], 'aisles.from'],
        'estanterías invertidas' => [['racks' => ['from' => 5, 'to' => 2]], 'racks.to'],
        'nivel cero' => [['levels' => ['from' => 0, 'to' => 2]], 'levels.from'],
        'nivel de tres dígitos' => [['levels' => ['from' => 1, 'to' => 100]], 'levels.to'],
        'posiciones incompletas' => [['positions' => ['from' => 1]], 'positions.to'],
        'clave desconocida en el rango' => [['racks' => ['from' => 1, 'to' => 2, 'step' => 2]], 'racks'],
        'zona inexistente' => [['zone_id' => 0], 'zone_id'],
        'lote demasiado grande' => [['aisles' => ['from' => 'A', 'to' => 'Z'], 'racks' => ['from' => 1, 'to' => 99]], 'aisles'],
    ]);

    it('rejects a system zone as the target of the template', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations/bulk", gridPayload(systemZoneOf($this->central)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['zone_id'], 'errors');
    });
});
