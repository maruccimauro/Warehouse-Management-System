<?php

namespace Tests\Support\Concurrency;

use App\Modules\Inventory\Data\MovementLine;
use App\Modules\Inventory\Data\OperationDraft;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockLedger;
use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Exceptions\DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Proceso hijo de las pruebas de concurrencia (E3.14). Lo registra
 * AppServiceProvider solo en el entorno testing; ConcurrentRun lanza N copias
 * en paralelo, cada una con su propia conexión.
 *
 * Abre la conexión, espera la barrera de inicio (el bloqueo consultivo que
 * tiene la prueba) y corre el escenario en su transacción, como una Action
 * (DB::transaction con tres intentos, ADR-004). Imprime una línea JSON con el
 * resultado: ok, rejected con el código de dominio, o error con la clase y el
 * mensaje. Termina con 0 en los tres casos: un rechazo es un resultado del
 * escenario, no una falla del proceso.
 */
final class ConcurrencyScenarioCommand extends Command
{
    /** Clave del bloqueo consultivo de la barrera (registrada en ADR-003). */
    public const BARRIER_KEY = 999001;

    protected $signature = 'testing:concurrency-scenario {scenario} {payload}';

    protected $description = 'Corre un escenario de las pruebas de concurrencia (solo en testing).';

    public function handle(StockLedger $ledger, DatabaseErrorTranslator $translator): int
    {
        $payload = json_decode((string) $this->argument('payload'), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new InvalidArgumentException('El payload es un objeto JSON.');
        }

        // La barrera: la prueba tiene el bloqueo exclusivo y lo suelta cuando
        // todos los hijos esperan el compartido.
        DB::select('SELECT pg_advisory_lock_shared(?)', [self::BARRIER_KEY]);
        DB::select('SELECT pg_advisory_unlock_shared(?)', [self::BARRIER_KEY]);

        $this->line((string) json_encode($this->outcome(fn (): InventoryOperation => DB::transaction(
            fn (): InventoryOperation => $ledger->apply($this->draft((string) $this->argument('scenario'), $payload)),
            attempts: 3,
        ), $translator)));

        return self::SUCCESS;
    }

    /**
     * @param  callable(): InventoryOperation  $scenario
     * @return array<string, mixed>
     */
    private function outcome(callable $scenario, DatabaseErrorTranslator $translator): array
    {
        try {
            return ['status' => 'ok', 'operation_id' => $scenario()->id];
        } catch (DomainException $exception) {
            return ['status' => 'rejected', 'code' => $exception->errorCode()];
        } catch (QueryException $exception) {
            $domain = $translator->translate($exception);

            return $domain !== null
                ? ['status' => 'rejected', 'code' => $domain->errorCode()]
                : ['status' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['status' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()];
        }
    }

    /**
     * Escenarios sobre StockLedger. Cuando existan las Actions de E3.04 a
     * E3.09, los escenarios las invocan en lugar de armar el borrador.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function draft(string $scenario, array $payload): OperationDraft
    {
        $int = static fn (string $key): int => is_numeric($payload[$key] ?? null) ? (int) $payload[$key] : throw new InvalidArgumentException("Falta {$key} en el payload.");

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
}
