<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Modules\Sample\SampleConflict;
use Tests\Fixtures\Modules\Sample\SampleRecord;
use Tests\Fixtures\Modules\Sample\SampleRequest;
use Tests\Fixtures\Modules\Sample\SampleRuleViolated;

Route::get('/sample/ping', fn () => response()->json(['pong' => true]))->name('sample.ping');

Route::prefix('/sample/errors')->group(function () {
    Route::get('/domain', fn () => throw new SampleRuleViolated('Faltan 3 unidades.', ['available' => 2, 'requested' => 5]));
    Route::get('/domain-default-detail', fn () => throw new SampleConflict);
    Route::post('/validation', fn (SampleRequest $request) => response()->noContent());
    Route::get('/unauthenticated', fn () => response()->noContent())->middleware('auth');
    Route::get('/forbidden', fn () => throw new AuthorizationException('No tenés permiso sobre este depósito.'));
    Route::get('/model-not-found', fn () => throw (new ModelNotFoundException)->setModel(SampleRecord::class, [7]));
    Route::get('/throttled', fn () => throw new ThrottleRequestsException(headers: ['Retry-After' => '60']));
    Route::get('/crash', fn () => throw new RuntimeException('Detalle interno que no debe salir.'));

    // Errores reales de PostgreSQL sobre una tabla temporal de la sesión.
    $violate = function (string $constraint): void {
        DB::statement("CREATE TEMP TABLE sample_items (quantity integer CONSTRAINT {$constraint} CHECK (quantity >= 0))");
        DB::insert('INSERT INTO sample_items (quantity) VALUES (-1)');
    };
    $raise = function (string $code): void {
        DB::statement("DO \$\$ BEGIN RAISE EXCEPTION '{$code}' USING ERRCODE = 'check_violation'; END \$\$");
    };

    Route::get('/constraint-mapped', fn () => $violate('sample_quantity_check'));
    Route::get('/constraint-unmapped', fn () => $violate('sample_unmapped_check'));
    Route::get('/trigger-mapped', fn () => $raise('SAMPLE_TRIGGER_CONFLICT'));
    Route::get('/trigger-unmapped', fn () => $raise('SAMPLE_UNMAPPED_TRIGGER'));
});
