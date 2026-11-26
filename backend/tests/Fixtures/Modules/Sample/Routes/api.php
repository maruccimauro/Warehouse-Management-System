<?php

use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Services\StockLedger;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Modules\Sample\SampleConflict;
use Tests\Fixtures\Modules\Sample\SampleRecord;
use Tests\Fixtures\Modules\Sample\SampleRecordListRequest;
use Tests\Fixtures\Modules\Sample\SampleRecordResource;
use Tests\Fixtures\Modules\Sample\SampleRequest;
use Tests\Fixtures\Modules\Sample\SampleRuleViolated;

Route::get('/sample/records', fn (SampleRecordListRequest $request) => SampleRecordResource::collection($request->paginate(SampleRecord::query())));

Route::get('/sample/ping', fn () => response()->json(['pong' => true]))->name('sample.ping');

// Idempotencia (E3.10): un retiro real con StockLedger, en la transacción de
// una Action anidada en la del middleware, y una ruta que choca con un
// deadlock en el primer intento para probar el reintento de la petición.
Route::middleware(['auth:sanctum', Idempotency::MIDDLEWARE])->prefix('/sample/idempotent')->group(function () {
    Route::post('/withdraw', function (Request $request) {
        $operation = DB::transaction(fn (): InventoryOperation => app(StockLedger::class)->apply(new OperationDraft(
            OperationType::AdjustmentOut,
            $request->integer('warehouse'),
            (int) $request->user()?->getAuthIdentifier(),
            [new MovementLine($request->integer('product'), $request->integer('location'), 711, -$request->integer('quantity'))],
            reasonCode: AdjustmentReason::Loss,
            idempotencyKey: Idempotency::keyOf($request),
        )), attempts: 3);

        return response()->json(['id' => $operation->id, 'number' => $operation->formattedNumber()], 201);
    });

    Route::post('/flaky', function () {
        static $calls = 0;
        $calls++;

        if ($calls === 1) {
            $deadlock = new PDOException('ERROR:  se ha detectado un bloqueo mutuo');
            $deadlock->errorInfo = ['40P01', 7, 'ERROR:  se ha detectado un bloqueo mutuo'];

            throw new QueryException('pgsql', 'SELECT 1', [], $deadlock);
        }

        return response()->json(['calls' => $calls], 201);
    });
});

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
    $duplicate = function (): void {
        DB::statement('CREATE TEMP TABLE sample_items (code varchar(20) CONSTRAINT sample_items_code_unique UNIQUE)');
        DB::insert("INSERT INTO sample_items (code) VALUES ('A-01'), ('A-01')");
    };
    $raise = function (string $code): void {
        DB::statement("DO \$\$ BEGIN RAISE EXCEPTION '{$code}' USING ERRCODE = 'check_violation'; END \$\$");
    };

    Route::get('/constraint-mapped', fn () => $violate('sample_quantity_check'));
    Route::get('/constraint-unmapped', fn () => $violate('sample_unmapped_check'));
    Route::get('/unique-mapped', fn () => $duplicate());
    Route::get('/trigger-mapped', fn () => $raise('SAMPLE_TRIGGER_CONFLICT'));
    Route::get('/trigger-unmapped', fn () => $raise('SAMPLE_UNMAPPED_TRIGGER'));
});
