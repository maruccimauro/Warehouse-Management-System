<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_reports_ok_when_the_database_responds(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok']]);
    }

    public function test_reports_service_unavailable_when_the_database_does_not_respond(): void
    {
        // Puerto sin servicio: la conexión se rechaza de inmediato.
        config(['database.connections.pgsql.host' => '127.0.0.1', 'database.connections.pgsql.port' => 1]);
        DB::purge('pgsql');

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'degraded', 'checks' => ['database' => 'unavailable']]);
    }
}
