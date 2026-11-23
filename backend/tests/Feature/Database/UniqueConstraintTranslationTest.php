<?php

use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Database\QueryException;

/*
| Restricciones únicas de E2 sobre datos que ingresa el usuario (E2.10): la
| base las rechaza aunque el Form Request no detecte el duplicado por una
| carrera, y el traductor las convierte en VALUE_ALREADY_EXISTS con el campo
| del formulario. Se provocan contra el esquema real para verificar que los
| nombres registrados en los providers coinciden con los de las migraciones.
*/

function translatedDuplicate(Closure $insertDuplicate): ?ValueAlreadyExists
{
    try {
        $insertDuplicate();
    } catch (QueryException $exception) {
        $translated = app(DatabaseErrorTranslator::class)->translate($exception);

        return $translated instanceof ValueAlreadyExists ? $translated : null;
    }

    throw new LogicException('La inserción duplicada no violó ninguna restricción.');
}

it('translates the unique constraints of E2 to VALUE_ALREADY_EXISTS with the form field', function (Closure $insertDuplicate, string $field) {
    $error = translatedDuplicate($insertDuplicate);

    expect($error)->toBeInstanceOf(ValueAlreadyExists::class)
        ->and($error?->field)->toBe($field)
        ->and($error?->context())->toBe(['field' => $field]);
})->with([
    'código de depósito' => [function (): void {
        (new CreateWarehouse)('CENTRAL', 'Central');
        Warehouse::query()->create(['code' => 'CENTRAL', 'name' => 'Otro']);
    }, 'code'],
    'código de zona por depósito' => [function (): void {
        $warehouse = (new CreateWarehouse)('CENTRAL', 'Central');
        $zone = $warehouse->zones()->firstOrFail();
        $warehouse->zones()->create(['code' => $zone->code, 'name' => 'Copia', 'type' => $zone->type]);
    }, 'code'],
    'código de ubicación por depósito' => [function (): void {
        $warehouse = (new CreateWarehouse)('CENTRAL', 'Central');
        $location = $warehouse->locations()->firstOrFail();
        $warehouse->locations()->create(['zone_id' => $location->zone_id, 'code' => $location->code]);
    }, 'code'],
    'email de usuario' => [function (): void {
        User::factory()->create(['email' => 'duplicado@wms.test']);
        User::factory()->create(['email' => 'duplicado@wms.test']);
    }, 'email'],
]);

it('allows the same zone and location code in different warehouses', function () {
    $central = (new CreateWarehouse)('CENTRAL', 'Central');
    $north = (new CreateWarehouse)('NORTE', 'Norte');

    expect($north->locations()->pluck('code')->sort()->values()->all())
        ->toBe($central->locations()->pluck('code')->sort()->values()->all());
});
