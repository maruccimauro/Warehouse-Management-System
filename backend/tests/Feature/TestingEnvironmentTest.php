<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Garantías del entorno de pruebas: PostgreSQL real, base aislada de la de
 * desarrollo y UTC en la aplicación y en la sesión de base de datos.
 */
class TestingEnvironmentTest extends TestCase
{
    public function test_runs_against_the_postgresql_testing_database(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('wms_testing', DB::selectOne('select current_database() as name')->name);
    }

    public function test_uses_utc_in_application_and_database_session(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertSame('UTC', DB::selectOne("select current_setting('TimeZone') as tz")->tz);
    }
}
