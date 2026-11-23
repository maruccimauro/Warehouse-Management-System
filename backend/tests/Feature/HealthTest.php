<?php

use Illuminate\Support\Facades\DB;

it('reports ok when the database responds', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok']]);
});

it('reports service unavailable when the database does not respond', function () {
    // Puerto sin servicio: la conexión se rechaza de inmediato.
    config(['database.connections.pgsql.host' => '127.0.0.1', 'database.connections.pgsql.port' => 1]);
    DB::purge('pgsql');

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'degraded', 'checks' => ['database' => 'unavailable']]);
});
