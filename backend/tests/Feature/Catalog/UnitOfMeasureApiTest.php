<?php

use App\Modules\Catalog\Actions\CreateUnitOfMeasure;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;

/*
| Unidades de medida (E2.05): código en mayúsculas, único e inmutable (las
| cantidades de los productos están expresadas en su unidad base, ADR-005).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->admin = userWithAccess(Role::Administrator);
    $this->unit = app(CreateUnitOfMeasure::class)('UN', 'Unidad');
});

it('lists units sorted by code to any session', function () {
    app(CreateUnitOfMeasure::class)('G', 'Gramo');

    $this->actingAs(userWithAccess(null))
        ->getJson('/api/v1/units')
        ->assertOk()
        ->assertJsonPath('data.*.code', ['G', 'UN'])
        ->assertJsonPath('data.1', ['id' => $this->unit->id, 'code' => 'UN', 'name' => 'Unidad']);
});

it('creates a unit with the code in uppercase', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/units', ['code' => ' ml ', 'name' => 'Mililitro'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'ML');
});

it('rejects a repeated or malformed code', function (string $code) {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/units', ['code' => $code, 'name' => 'Otra'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code'], 'errors');
})->with(['un', 'U N', 'UNIDADES-DE-MEDIDA']);

it('renames a unit and keeps its code', function () {
    $this->actingAs($this->admin)
        ->patchJson("/api/v1/units/{$this->unit->id}", ['name' => 'Unidad suelta'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Unidad suelta');

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/units/{$this->unit->id}", ['code' => 'PZ', 'name' => 'Pieza'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code'], 'errors');

    expect($this->unit->refresh()->code)->toBe('UN');
});

it('requires catalog.manage to create or rename', function () {
    $purchasing = userWithAccess(Role::Purchasing);

    $this->actingAs($purchasing)->postJson('/api/v1/units', ['code' => 'KG', 'name' => 'Kilogramo'])->assertForbidden();
    $this->actingAs($purchasing)->patchJson("/api/v1/units/{$this->unit->id}", ['name' => 'Otra'])->assertForbidden();
});
