<?php

namespace Tests\Support\Concurrency;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockLedger;
use App\Shared\Exceptions\ProblemDetailsRenderer;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Fixtures\Modules\Sample\SampleServiceProvider;
use Throwable;

/**
 * Proceso hijo de las pruebas de concurrencia (E3.14). Lo registra
 * AppServiceProvider solo en el entorno testing; ConcurrentRun lanza N copias
 * en paralelo, cada una con su propia conexión.
 *
 * Abre la conexión, espera la barrera de inicio (el bloqueo consultivo que
 * tiene la prueba) y corre el escenario en su transacción, como una Action
 * (DB::transaction con tres intentos por defecto, ADR-004). Imprime una línea
 * JSON con el resultado y los intentos usados: ok; rejected con el código que
 * respondería la API, calculado por ProblemDetailsRenderer; o error con la
 * clase y el mensaje si la API respondería 500. Termina con 0 en los tres
 * casos: un rechazo es un resultado del escenario, no una falla del proceso.
 */
final class ConcurrencyScenarioCommand extends Command
{
    /** Clave del bloqueo consultivo de la barrera (registrada en ADR-003). */
    public const BARRIER_KEY = 999001;

    /** Pausa entre el primer y el segundo bloqueo del escenario deadlock. */
    private const DEADLOCK_PAUSE_SECONDS = 0.5;

    protected $signature = 'testing:concurrency-scenario {scenario} {payload}';

    protected $description = 'Corre un escenario de las pruebas de concurrencia (solo en testing).';

    public function handle(StockLedger $ledger, ProblemDetailsRenderer $problems): int
    {
        $payload = json_decode((string) $this->argument('payload'), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new InvalidArgumentException('El payload es un objeto JSON.');
        }

        $scenario = (string) $this->argument('scenario');

        if ($scenario === 'http-withdraw') {
            return $this->httpWithdraw($payload);
        }

        $work = $scenario === 'deadlock'
            ? fn (): ?int => $this->crossLocks($payload)
            : fn (): ?int => $ledger->apply($this->draft($scenario, $payload))->id;
        $attempts = 0;

        // La barrera: la prueba tiene el bloqueo exclusivo y lo suelta cuando
        // todos los hijos esperan el compartido.
        DB::select('SELECT pg_advisory_lock_shared(?)', [self::BARRIER_KEY]);
        DB::select('SELECT pg_advisory_unlock_shared(?)', [self::BARRIER_KEY]);

        try {
            $operationId = DB::transaction(function () use ($work, &$attempts): ?int {
                $attempts++;

                return $work();
            }, attempts: self::int($payload, 'attempts', 3));

            $outcome = ['status' => 'ok', 'operation_id' => $operationId];
        } catch (Throwable $exception) {
            $problem = $problems->toProblem($exception);

            $outcome = $problem->status >= 500 && $problem->code === 'INTERNAL_ERROR'
                ? ['status' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()]
                : ['status' => 'rejected', 'code' => $problem->code, 'http_status' => $problem->status];
        }

        $this->line((string) json_encode([...$outcome, 'attempts' => $attempts]));

        return self::SUCCESS;
    }

    /**
     * Retiro por HTTP con Idempotency-Key (E3.10): la petición atraviesa el
     * kernel completo, con el middleware idempotent dueño de la transacción.
     * El usuario se fija en el guard web, que Sanctum consulta primero.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function httpWithdraw(array $payload): int
    {
        $this->laravel->register(SampleServiceProvider::class);
        Auth::guard('web')->setUser(User::query()->findOrFail(self::int($payload, 'user')));

        $request = Request::create('/api/v1/sample/idempotent/withdraw', 'POST', server: [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.strtoupper(str_replace('-', '_', Idempotency::HEADER)) => (string) ($payload['key'] ?? ''),
        ], content: (string) json_encode([
            'warehouse' => self::int($payload, 'warehouse'),
            'product' => self::int($payload, 'product'),
            'location' => self::int($payload, 'location'),
            'quantity' => self::int($payload, 'quantity'),
        ]));

        DB::select('SELECT pg_advisory_lock_shared(?)', [self::BARRIER_KEY]);
        DB::select('SELECT pg_advisory_unlock_shared(?)', [self::BARRIER_KEY]);

        $response = $this->laravel->make(Kernel::class)->handle($request);
        $body = json_decode((string) $response->getContent(), true);

        $this->line((string) json_encode([
            'status' => 'ok',
            'http_status' => $response->getStatusCode(),
            'body' => $body,
            'replayed' => $response->headers->get(Idempotency::REPLAYED_HEADER) === 'true',
        ]));

        return self::SUCCESS;
    }

    /**
     * Deadlock real (E3.19): toma el bloqueo consultivo first, espera a que el
     * otro proceso tome el suyo y pide second. Dos procesos con las claves
     * cruzadas se esperan mutuamente y PostgreSQL aborta a uno con 40P01.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function crossLocks(array $payload): ?int
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [self::int($payload, 'first')]);
        DB::select('SELECT pg_sleep(?)', [self::DEADLOCK_PAUSE_SECONDS]);
        DB::select('SELECT pg_advisory_xact_lock(?)', [self::int($payload, 'second')]);

        return null;
    }

    /**
     * Escenarios sobre StockLedger. Cuando existan las Actions de E3.04 a
     * E3.09, los escenarios las invocan en lugar de armar el borrador.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function draft(string $scenario, array $payload): OperationDraft
    {
        $int = static fn (string $key): int => self::int($payload, $key);

        $warehouse = $int('warehouse');
        $user = $int('user');

        return match ($scenario) {
            'withdraw' => new OperationDraft(OperationType::AdjustmentOut, $warehouse, $user, [
                new MovementLine($int('product'), $int('location'), 711, -$int('quantity')),
            ], reasonCode: AdjustmentReason::Loss),
            'enter' => new OperationDraft(OperationType::AdjustmentIn, $warehouse, $user, [
                new MovementLine($int('product'), $int('location'), 712, $int('quantity')),
            ], reasonCode: AdjustmentReason::Found),
            'transfer' => new OperationDraft(OperationType::Transfer, $warehouse, $user, [
                new MovementLine($int('product'), $int('from'), 931, -$int('quantity')),
                new MovementLine($int('product'), $int('to'), 931, $int('quantity')),
            ]),
            'reverse' => new OperationDraft(OperationType::Reversal, $warehouse, $user, StockMovement::query()
                ->where('operation_id', $int('operation'))
                ->orderBy('id')
                ->get()
                ->map(fn (StockMovement $line): MovementLine => new MovementLine(
                    $line->product_id,
                    $line->location_id,
                    (int) $line->movementType()?->reversal_code,
                    $line->delta > 0 ? -$int('quantity') : $int('quantity'),
                    reversesMovementId: $line->id,
                ))
                ->all(), reversesOperationId: $int('operation'), notes: 'Reversión concurrente'),
            default => throw new InvalidArgumentException("Escenario desconocido: {$scenario}."),
        };
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function int(array $payload, string $key, ?int $default = null): int
    {
        $value = $payload[$key] ?? $default;

        return is_numeric($value) ? (int) $value : throw new InvalidArgumentException("Falta {$key} en el payload.");
    }
}
