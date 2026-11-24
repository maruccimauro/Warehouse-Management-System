<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Actions\LockActiveWarehouse;
use App\Modules\Warehouse\Actions\UpdateWarehouse;
use App\Modules\Warehouse\Exceptions\WarehouseInactive;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordingAuditLogger;

/*
| ABM de depósitos: consulta por alcance (REG-005), alta y edición con
| warehouses.manage, código inmutable (DM-19) y desactivación sin borrado
| con auditoría (REG-015, INV-012). Un depósito inactivo se consulta y admite
| el mantenimiento de zonas y ubicaciones; las operaciones se prueban en
| LocationBlockingTest.
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->audit = new RecordingAuditLogger;
    app()->instance(AuditLogger::class, $this->audit);

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central', 'Av. Industrial 1200');
    $this->norte = app(CreateWarehouse::class)('NORTE', 'Depósito norte');
    $this->admin = userWithAccess(Role::Administrator);
});

describe('queries', function () {
    it('lists every visible warehouse, inactive ones included, sorted by code', function () {
        $this->norte->update(['is_active' => false]);

        $this->actingAs(userWithAccess(Role::Operator, [$this->central]))
            ->getJson('/api/v1/warehouses')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.*.code', ['CENTRAL', 'NORTE'])
            ->assertJsonPath('data.1.is_active', false)
            ->assertJsonPath('data.0', [
                'id' => $this->central->id,
                'code' => 'CENTRAL',
                'name' => 'Depósito central',
                'address' => 'Av. Industrial 1200',
                'is_active' => true,
                'created_at' => $this->central->refresh()->created_at->toJSON(),
                'updated_at' => $this->central->updated_at->toJSON(),
            ]);
    });

    it('lists only the assigned warehouses without warehouses.view_all', function () {
        $this->actingAs(userWithAccess(null, [$this->norte]))
            ->getJson('/api/v1/warehouses')
            ->assertOk()
            ->assertJsonPath('data.*.code', ['NORTE']);
    });

    it('filters by active flag and name fragment, and sorts by name', function () {
        $this->norte->update(['is_active' => false]);
        app(CreateWarehouse::class)('SUR', 'Anexo sur');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/warehouses?filter[is_active]=1&sort=-name')
            ->assertJsonPath('data.*.code', ['CENTRAL', 'SUR']);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/warehouses?filter[name]=NORTE')
            ->assertJsonPath('data.*.code', ['NORTE']);
    });

    it('rejects an undeclared filter or an invalid active flag', function (string $query, string $field) {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/warehouses?{$query}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'filtro no declarado' => ['filter[address]=Av', 'filter'],
        'marca inválida' => ['filter[is_active]=quizás', 'filter.is_active'],
        'orden no declarado' => ['sort=address', 'sort'],
    ]);

    it('shows a visible warehouse and answers 404 outside the query scope', function () {
        $this->actingAs(userWithAccess(null, [$this->central]))
            ->getJson("/api/v1/warehouses/{$this->central->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'CENTRAL');

        $this->actingAs(userWithAccess(null, [$this->central]))
            ->getJson("/api/v1/warehouses/{$this->norte->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    });

    it('answers 404 for an identifier that is not a valid BIGINT', function (string $id) {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/warehouses/{$id}")
            ->assertNotFound();
    })->with(['abc', '0', '99999999999999999999']);

    it('requires a session', function () {
        $this->getJson('/api/v1/warehouses')->assertUnauthorized();
    });
});

describe('creation', function () {
    it('creates an active warehouse with its system zones and the code in uppercase', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/warehouses', ['code' => ' sur-1 ', 'name' => 'Depósito sur', 'address' => 'Ruta 3 km 40'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SUR-1')
            ->assertJsonPath('data.address', 'Ruta 3 km 40')
            ->assertJsonPath('data.is_active', true);

        $warehouse = Warehouse::query()->findOrFail($response->json('data.id'));

        expect($warehouse->zones()->orderBy('code')->pluck('code')->all())->toBe(['DESPACHO', 'PACKING', 'RECEPCION'])
            ->and($warehouse->locations()->count())->toBe(3);
    });

    it('creates a warehouse without address', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/warehouses', ['code' => 'SUR', 'name' => 'Depósito sur'])
            ->assertCreated()
            ->assertJsonPath('data.address', null);
    });

    it('rejects an invalid or repeated code', function (array $body, string $field) {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/warehouses', $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors([$field], 'errors');

        expect(Warehouse::query()->count())->toBe(2);
    })->with([
        'código repetido sin distinguir mayúsculas' => [['code' => 'central', 'name' => 'Otro'], 'code'],
        'código con espacios internos' => [['code' => 'SUR 1', 'name' => 'Sur'], 'code'],
        'código demasiado largo' => [['code' => str_repeat('A', 21), 'name' => 'Sur'], 'code'],
        'sin nombre' => [['code' => 'SUR'], 'name'],
        'dirección demasiado larga' => [['code' => 'SUR', 'name' => 'Sur', 'address' => str_repeat('x', 501)], 'address'],
    ]);

    it('requires warehouses.manage, authorizing before validating', function () {
        $this->actingAs(userWithAccess(Role::Supervisor, [$this->central]))
            ->postJson('/api/v1/warehouses', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    });
});

describe('update', function () {
    it('updates the name and the address, and clears the address with null', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/warehouses/{$this->central->id}", ['name' => 'Central renovado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Central renovado')
            ->assertJsonPath('data.address', 'Av. Industrial 1200');

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/warehouses/{$this->central->id}", ['address' => null])
            ->assertOk()
            ->assertJsonPath('data.address', null);

        expect($this->audit->records)->toBe([]);
    });

    it('never changes the code (DM-19)', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/warehouses/{$this->central->id}", ['code' => 'CENTRAL2'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'], 'errors');

        expect($this->central->refresh()->code)->toBe('CENTRAL');
    });

    it('rejects an invalid active flag or an empty name', function (array $body, string $field) {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/warehouses/{$this->central->id}", $body)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'marca inválida' => [['is_active' => 'quizás'], 'is_active'],
        'marca nula' => [['is_active' => null], 'is_active'],
        'nombre vacío' => [['name' => ''], 'name'],
    ]);

    it('deactivates and reactivates a warehouse, auditing each change inside the transaction', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/warehouses/{$this->central->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/warehouses/{$this->central->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        expect($this->audit->records)->toHaveCount(2)
            ->and($this->audit->records[0])
            ->action->toBe('warehouse.deactivated')
            ->warehouse_id->toBe($this->central->id)
            ->before->toBe(['is_active' => true])
            ->after->toBe(['is_active' => false])
            ->and($this->audit->records[1])
            ->action->toBe('warehouse.activated')
            ->before->toBe(['is_active' => false])
            ->after->toBe(['is_active' => true])
            ->and($this->audit->records[0]['auditable']->is($this->central))->toBeTrue();
    });

    it('audits inside the transaction of the change', function () {
        $baseline = DB::transactionLevel();

        app(UpdateWarehouse::class)($this->central, [], false);

        expect($this->audit->records[0]['transaction_level'])->toBeGreaterThan($baseline);
    });

    it('does not audit an unchanged active flag', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/warehouses/{$this->central->id}", ['is_active' => true, 'name' => 'Central'])
            ->assertOk();

        expect($this->audit->records)->toBe([]);
    });

    it('requires warehouses.manage in an operable warehouse', function () {
        $this->actingAs(userWithAccess(Role::Supervisor, [$this->central]))
            ->patchJson("/api/v1/warehouses/{$this->central->id}", ['is_active' => false])
            ->assertForbidden();

        $this->actingAs(userWithAccess(null, [$this->central]))
            ->patchJson("/api/v1/warehouses/{$this->norte->id}", ['is_active' => false])
            ->assertNotFound();

        expect($this->central->refresh()->is_active)->toBeTrue()
            ->and($this->norte->refresh()->is_active)->toBeTrue();
    });
});

describe('inactive warehouse (REG-015)', function () {
    beforeEach(function () {
        $this->central->update(['is_active' => false]);
    });

    it('admits the maintenance of zones and locations', function () {
        $zoneId = $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/zones", ['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => 'storage'])
            ->assertCreated()
            ->json('data.id');

        $locationId = $this->actingAs($this->admin)
            ->postJson("/api/v1/warehouses/{$this->central->id}/locations", ['code' => 'A-01-01', 'zone_id' => $zoneId])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/locations/{$locationId}", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    });

    it('rejects an operation through LockActiveWarehouse', function () {
        expect(fn () => DB::transaction(fn () => app(LockActiveWarehouse::class)($this->central->id)))
            ->toThrow(WarehouseInactive::class);

        expect(DB::transaction(fn () => app(LockActiveWarehouse::class)($this->norte->id))->is($this->norte))->toBeTrue();
    });
});
