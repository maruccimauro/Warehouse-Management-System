<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Warehouse\Actions\BlockLocation;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordingAuditLogger;

/*
| Bloqueo y desbloqueo de ubicaciones (E2.04): máquina de estados de Location
| en domain-model.md, motivo obligatorio y auditoría dentro de la transacción
| (INV-012, ADR-008). El alcance por depósito se prueba en WarehouseAccessTest.
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->audit = new RecordingAuditLogger;
    app()->instance(AuditLogger::class, $this->audit);

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $zone = $this->central->zones()->create(['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => ZoneType::Storage]);
    $this->location = $this->central->locations()->create(['zone_id' => $zone->id, 'code' => 'A-01-01']);
    $this->supervisor = userWithAccess(Role::Supervisor, [$this->central]);
});

function blockedLocation(Location $location, string $reason = 'Rack dañado'): Location
{
    $location->forceFill(['status' => LocationStatus::Blocked, 'status_reason' => $reason])->save();

    return $location;
}

describe('block', function () {
    it('blocks an active location with its reason', function () {
        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/locations/{$this->location->id}/block", ['reason' => '  Rack dañado  '])
            ->assertOk()
            ->assertJsonPath('data.id', $this->location->id)
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.status_reason', 'Rack dañado');

        expect($this->location->refresh())
            ->status->toBe(LocationStatus::Blocked)
            ->status_reason->toBe('Rack dañado');
    });

    it('requires a reason of at most 255 characters', function (array $payload) {
        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/locations/{$this->location->id}/block", $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['reason'], 'errors');

        expect($this->location->refresh()->status)->toBe(LocationStatus::Active);
    })->with([
        'sin motivo' => [[]],
        'motivo vacío' => [['reason' => '   ']],
        'motivo no textual' => [['reason' => ['Rack dañado']]],
        'motivo demasiado largo' => [['reason' => str_repeat('x', 256)]],
    ]);

    it('authorizes before validating the reason', function () {
        $this->actingAs(userWithAccess(Role::Operator, [$this->central]))
            ->postJson("/api/v1/locations/{$this->location->id}/block")
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    });

    it('rejects with 409 blocking a location that is not active', function (LocationStatus $current) {
        $this->location->forceFill(['status' => $current, 'status_reason' => 'Motivo anterior'])->save();

        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/locations/{$this->location->id}/block", ['reason' => 'Rack dañado'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATE_TRANSITION')
            ->assertJsonPath('context', ['from' => $current->value, 'to' => 'blocked']);

        expect($this->location->refresh())
            ->status->toBe($current)
            ->status_reason->toBe('Motivo anterior')
            ->and($this->audit->records)->toBe([]);
    })->with([LocationStatus::Blocked, LocationStatus::Counting, LocationStatus::Inactive]);

    it('audits the block inside the transaction with the values before and after', function () {
        $baseline = DB::transactionLevel();

        app(BlockLocation::class)($this->location, 'Rack dañado');

        expect($this->audit->records)->toHaveCount(1)
            ->and($this->audit->records[0])
            ->action->toBe('location.blocked')
            ->warehouse_id->toBe($this->central->id)
            ->before->toBe(['status' => 'active', 'status_reason' => null])
            ->after->toBe(['status' => 'blocked', 'status_reason' => 'Rack dañado'])
            ->transaction_level->toBeGreaterThan($baseline)
            ->and($this->audit->records[0]['auditable']->is($this->location))->toBeTrue();
    });
});

describe('unblock', function () {
    it('unblocks a blocked location and clears the reason', function () {
        blockedLocation($this->location);

        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/locations/{$this->location->id}/unblock")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.status_reason', null);

        expect($this->location->refresh())
            ->status->toBe(LocationStatus::Active)
            ->status_reason->toBeNull()
            ->and($this->audit->records)->toHaveCount(1)
            ->and($this->audit->records[0])
            ->action->toBe('location.unblocked')
            ->before->toBe(['status' => 'blocked', 'status_reason' => 'Rack dañado'])
            ->after->toBe(['status' => 'active', 'status_reason' => null]);
    });

    it('rejects with 409 unblocking a location that is not blocked', function (LocationStatus $current) {
        $this->location->forceFill(['status' => $current])->save();

        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/locations/{$this->location->id}/unblock")
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATE_TRANSITION')
            ->assertJsonPath('context', ['from' => $current->value, 'to' => 'active']);

        expect($this->location->refresh()->status)->toBe($current)
            ->and($this->audit->records)->toBe([]);
    })->with([LocationStatus::Active, LocationStatus::Counting, LocationStatus::Inactive]);

    it('requires locations.block in an operable warehouse', function () {
        blockedLocation($this->location);
        $norte = app(CreateWarehouse::class)('NORTE', 'Depósito norte');

        $this->actingAs(userWithAccess(Role::Operator, [$this->central]))
            ->postJson("/api/v1/locations/{$this->location->id}/unblock")
            ->assertForbidden()
            ->assertJsonPath('detail', __('identity.permission_denied'));

        $this->actingAs(userWithAccess(null, [$norte]))
            ->postJson("/api/v1/locations/{$this->location->id}/unblock")
            ->assertNotFound();

        expect($this->location->refresh()->status)->toBe(LocationStatus::Blocked);
    });
});

describe('inactive warehouse (REG-015)', function () {
    it('rejects with 422 blocking or unblocking a location of an inactive warehouse', function (string $action, LocationStatus $current) {
        $this->location->forceFill(['status' => $current, 'status_reason' => $current === LocationStatus::Blocked ? 'Rack dañado' : null])->save();
        $this->central->update(['is_active' => false]);

        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/locations/{$this->location->id}/{$action}", ['reason' => 'Rack dañado'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'WAREHOUSE_INACTIVE')
            ->assertJsonPath('detail', __('warehouse.warehouse_inactive.detail', ['code' => 'CENTRAL']))
            ->assertJsonPath('context', ['warehouse_id' => $this->central->id]);

        expect($this->location->refresh()->status)->toBe($current)
            ->and($this->audit->records)->toBe([]);
    })->with([
        'bloqueo' => ['block', LocationStatus::Active],
        'desbloqueo' => ['unblock', LocationStatus::Blocked],
    ]);

    it('checks the warehouse before the state of the location', function () {
        $this->location->forceFill(['status' => LocationStatus::Counting])->save();
        $this->central->update(['is_active' => false]);

        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/locations/{$this->location->id}/block", ['reason' => 'Rack dañado'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'WAREHOUSE_INACTIVE');
    });

    it('keeps the authorization before the warehouse rule', function () {
        $this->central->update(['is_active' => false]);

        $this->actingAs(userWithAccess(Role::Operator, [$this->central]))
            ->postJson("/api/v1/locations/{$this->location->id}/block", ['reason' => 'Rack dañado'])
            ->assertForbidden();
    });
});

it('answers 404 for a location id that does not exist or is not numeric', function (string $id) {
    $this->actingAs($this->supervisor)
        ->postJson("/api/v1/locations/{$id}/unblock")
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
})->with(['999999', 'abc', '99999999999999999999']);

it('registers a stable morph alias for audited warehouse models', function () {
    expect($this->location->getMorphClass())->toBe('location')
        ->and($this->central->getMorphClass())->toBe('warehouse')
        ->and($this->location->zone->getMorphClass())->toBe('zone');
});
