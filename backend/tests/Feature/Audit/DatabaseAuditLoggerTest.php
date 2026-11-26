<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\DatabaseAuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Http\RequestContext;
use Illuminate\Support\Facades\DB;

/*
| Auditoría en base de datos (E3.11, INV-012, ADR-008): DatabaseAuditLogger
| escribe en audit_logs dentro de la transacción del cambio, con el usuario,
| la IP y el identificador de la petición. Que toda operación de inventario
| tenga su registro se verifica al final de cada prueba (tests/Pest.php).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $zone = $this->central->zones()->create(['code' => 'ALM', 'name' => 'Almacenamiento', 'type' => ZoneType::Storage]);
    $this->location = $this->central->locations()->create(['zone_id' => $zone->id, 'code' => 'A-01-01']);
    $this->admin = userWithAccess(Role::Administrator, [$this->central]);
});

it('is the binding of the audit contract', function () {
    expect(app(AuditLogger::class))->toBeInstanceOf(DatabaseAuditLogger::class);
});

it('records a warehouse action with the user, the ip and the request id of the request', function () {
    $requestId = '0193a4b2-7c1d-7e2f-9a3b-4c5d6e7f8a9b';

    $response = $this->actingAs($this->admin)
        ->withHeader(RequestContext::HEADER, $requestId)
        ->postJson("/api/v1/locations/{$this->location->id}/block", ['reason' => 'Rack dañado'])
        ->assertOk()
        ->assertHeader(RequestContext::HEADER, $requestId);

    $log = AuditLog::query()->sole();

    expect($log)
        ->action->toBe('location.blocked')
        ->auditable_type->toBe('location')
        ->auditable_id->toBe($this->location->id)
        ->warehouse_id->toBe($this->central->id)
        ->user_id->toBe($this->admin->id)
        ->ip->toBe('127.0.0.1')
        ->request_id->toBe($response->headers->get(RequestContext::HEADER))
        ->operation_id->toBeNull()
        ->occurred_at->not->toBeNull()
        ->and($log->before)->toMatchArray(['status' => 'active', 'status_reason' => null])
        ->and($log->after)->toMatchArray(['status' => 'blocked', 'status_reason' => 'Rack dañado']);
});

it('records a global action without warehouse', function () {
    $user = userWithAccess(Role::Operator);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$user->id}", ['roles' => [Role::Supervisor->value]])
        ->assertOk();

    expect(AuditLog::query()->sole())
        ->action->toBe('user.roles_changed')
        ->auditable_type->toBe('user')
        ->auditable_id->toBe($user->id)
        ->warehouse_id->toBeNull()
        ->before->toBe(['roles' => ['operator']])
        ->after->toBe(['roles' => ['supervisor']]);
});

it('records an inventory operation with its operation id', function () {
    $product = Product::factory()->create();

    $operation = DB::transaction(fn () => app(StockLedger::class)->apply(new OperationDraft(
        type: OperationType::AdjustmentIn,
        warehouseId: $this->central->id,
        performedBy: $this->admin->id,
        lines: [new MovementLine($product->id, $this->location->id, 561, 10)],
        reasonCode: AdjustmentReason::InitialLoad,
    )));

    expect(AuditLog::query()->sole())
        ->action->toBe('inventory_operation.created')
        ->auditable_type->toBe('inventory_operation')
        ->auditable_id->toBe($operation->id)
        ->operation_id->toBe($operation->id)
        ->warehouse_id->toBe($this->central->id);
});

it('leaves no record when the transaction of the change rolls back', function () {
    try {
        DB::transaction(function () {
            app(AuditLogger::class)->record('location.blocked', $this->location, $this->central->id, null, null);

            throw new RuntimeException('Falla después de auditar.');
        });
    } catch (RuntimeException) {
        //
    }

    expect(AuditLog::query()->count())->toBe(0);
});

it('leaves user, ip and request id empty outside an http request', function () {
    app(AuditLogger::class)->record('location.blocked', $this->location, $this->central->id, null, null);

    expect(AuditLog::query()->sole())
        ->user_id->toBeNull()
        ->ip->toBeNull()
        ->request_id->toBeNull();
});

it('rejects an entity that is not persisted', function () {
    app(AuditLogger::class)->record('location.blocked', new Location, $this->central->id, null, null);
})->throws(LogicException::class);

it('never records a password', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$user->id}", ['password' => 'otra-clave-inicial'])
        ->assertOk();

    expect(AuditLog::query()->sole())
        ->action->toBe('user.password_reset')
        ->before->toBeNull()
        ->after->toBeNull();
});
