<?php

use Illuminate\Support\Facades\DB;

/*
| Garantías del entorno de pruebas: PostgreSQL real, base aislada de la de
| desarrollo y UTC en la aplicación y en la sesión de base de datos.
*/

it('runs against the postgresql testing database', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne('select current_database() as name')->name)->toBe('wms_testing');
});

it('uses utc in the application and in the database session', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(date_default_timezone_get())->toBe('UTC')
        ->and(DB::selectOne("select current_setting('TimeZone') as tz")->tz)->toBe('UTC');
});
