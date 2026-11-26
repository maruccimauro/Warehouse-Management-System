<?php

namespace Tests\Support\Concurrency;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Lanza N procesos hijos de ConcurrencyScenarioCommand en paralelo y espera
 * todos con un timeout explícito (E3.14).
 *
 * Barrera de inicio: antes de lanzarlos, la prueba toma el bloqueo consultivo
 * exclusivo BARRIER_KEY; cada hijo arranca Laravel, abre su conexión y pide
 * el compartido. Cuando pg_locks muestra los N pedidos en espera, la prueba lo
 * suelta y todos empiezan a la vez, lo que maximiza la colisión. Un hijo que
 * termina antes de llegar a la barrera, o una barrera que no se completa a
 * tiempo, hacen fallar la prueba con la salida del hijo.
 */
final class ConcurrentRun
{
    /** Variables del entorno de pruebas que los hijos no heredan de phpunit.xml. */
    private const CHILD_ENVIRONMENT = [
        'APP_ENV' => 'testing',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'PULSE_ENABLED' => 'false',
        'TELESCOPE_ENABLED' => 'false',
        'NIGHTWATCH_ENABLED' => 'false',
    ];

    private const POLL_INTERVAL_MICROSECONDS = 20_000;

    /**
     * @param  list<array<string, int>>  $payloads  Uno por proceso
     * @return list<array<string, mixed>> Resultado de cada proceso, en el orden de los payloads
     */
    public static function scenario(string $scenario, array $payloads, int $timeoutSeconds = 120): array
    {
        $environment = [...self::CHILD_ENVIRONMENT, 'DB_DATABASE' => (string) config('database.connections.pgsql.database')];
        $processes = [];

        DB::select('SELECT pg_advisory_lock(?)', [ConcurrencyScenarioCommand::BARRIER_KEY]);

        try {
            foreach ($payloads as $payload) {
                $process = new Process(
                    [PHP_BINARY, base_path('artisan'), 'testing:concurrency-scenario', $scenario, (string) json_encode($payload)],
                    base_path(),
                    $environment,
                    timeout: $timeoutSeconds,
                );
                $process->start();
                $processes[] = $process;
            }

            self::awaitBarrier($processes, $timeoutSeconds);
        } catch (Throwable $exception) {
            foreach ($processes as $process) {
                $process->stop(0);
            }

            throw $exception;
        } finally {
            DB::select('SELECT pg_advisory_unlock(?)', [ConcurrencyScenarioCommand::BARRIER_KEY]);
        }

        return array_map(self::result(...), $processes);
    }

    /**
     * @param  list<Process>  $processes
     */
    private static function awaitBarrier(array $processes, int $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (self::waitingAtBarrier() < count($processes)) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    throw new RuntimeException('Un proceso terminó antes de la barrera: '.$process->getOutput().$process->getErrorOutput());
                }
            }

            if (microtime(true) > $deadline) {
                throw new RuntimeException('La barrera no se completó en '.$timeoutSeconds.' segundos.');
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }
    }

    /**
     * Pedidos del bloqueo compartido en espera. Una clave bigint ocupa
     * classid (32 bits altos) y objid (bajos), con objsubid 1.
     */
    private static function waitingAtBarrier(): int
    {
        $waiting = DB::scalar(
            "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND classid = 0 AND objid = ? AND objsubid = 1 AND mode = 'ShareLock' AND NOT granted",
            [ConcurrencyScenarioCommand::BARRIER_KEY],
        );

        return is_numeric($waiting) ? (int) $waiting : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function result(Process $process): array
    {
        $process->wait();

        $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];
        $result = json_decode((string) end($lines), true);

        if (! $process->isSuccessful() || ! is_array($result)) {
            throw new RuntimeException('El proceso hijo falló: '.$process->getOutput().$process->getErrorOutput());
        }

        /** @var array<string, mixed> $result */
        return $result;
    }
}
