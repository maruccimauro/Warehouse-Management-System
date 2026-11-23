<?php

use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Enums\SystemLocationRole;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Depósitos con sus zonas y ubicaciones de sistema (E2.02) y las restricciones
| de la base sobre ubicaciones (database-design.md).
*/

function createWarehouse(string $code = 'central'): Warehouse
{
    return app(CreateWarehouse::class)($code, 'Depósito central', 'Av. Industrial 1200');
}

it('creates a warehouse with its system zones and locations', function () {
    $warehouse = createWarehouse();

    expect($warehouse->code)->toBe('CENTRAL')
        ->and($warehouse->refresh()->is_active)->toBeTrue();

    $zones = $warehouse->zones()->orderBy('code')->get();
    expect($zones->pluck('code')->all())->toBe(['DESPACHO', 'PACKING', 'RECEPCION'])
        ->and($zones->every(fn (Zone $zone): bool => $zone->is_system))->toBeTrue()
        ->and($zones->firstWhere('code', 'RECEPCION')?->type)->toBe(ZoneType::Receiving);

    foreach (SystemLocationRole::cases() as $role) {
        $location = Location::query()->where('warehouse_id', $warehouse->id)->where('system_role', $role->value)->sole();

        expect($location->code)->toBe($role->code())
            ->and($location->is_system)->toBeTrue()
            ->and($location->status)->toBe(LocationStatus::Active)
            ->and($location->zone?->code)->toBe($role->code());
    }
});

it('rejects a duplicate warehouse code without leaving partial data', function () {
    createWarehouse();

    expect(fn () => createWarehouse())->toThrow(QueryException::class, 'warehouses_code_unique');

    expect(Warehouse::query()->count())->toBe(1)
        ->and(Zone::query()->count())->toBe(3)
        ->and(Location::query()->count())->toBe(3);
});

it('allows a single location per system role and warehouse', function () {
    $warehouse = createWarehouse();
    $zone = $warehouse->zones()->where('code', 'RECEPCION')->sole();

    $warehouse->locations()->create(['zone_id' => $zone->id, 'code' => 'RECEPCION-2', 'system_role' => SystemLocationRole::Receiving]);
})->throws(QueryException::class, 'locations_warehouse_id_system_role_unique');

it('rejects a location whose zone belongs to another warehouse', function () {
    $central = createWarehouse('central');
    $north = createWarehouse('norte');
    $northZone = $north->zones()->where('code', 'RECEPCION')->sole();

    DB::table('locations')->insert(['warehouse_id' => $central->id, 'zone_id' => $northZone->id, 'code' => 'A01']);
})->throws(QueryException::class, 'locations_zone_id_warehouse_id_foreign');

it('requires a reason to block a location', function () {
    $warehouse = createWarehouse();

    $warehouse->locations()->where('code', 'RECEPCION')->update(['status' => LocationStatus::Blocked->value]);
})->throws(QueryException::class, 'locations_blocked_reason_check');

it('does not allow deactivating a system location', function () {
    $warehouse = createWarehouse();

    $warehouse->locations()->where('code', 'PACKING')->update(['status' => LocationStatus::Inactive->value]);
})->throws(QueryException::class, 'locations_system_not_inactive_check');

it('rejects lowercase codes written without the model', function () {
    DB::table('warehouses')->insert(['code' => 'central', 'name' => 'Depósito central']);
})->throws(QueryException::class, 'warehouses_code_uppercase_check');

it('seeds the development warehouses idempotently', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Warehouse::query()->orderBy('code')->pluck('code')->all())->toBe(['CENTRAL', 'NORTE'])
        ->and(Location::query()->where('is_system', true)->count())->toBe(6);
});
