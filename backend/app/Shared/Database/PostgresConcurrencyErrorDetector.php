<?php

namespace App\Shared\Database;

use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\ConcurrencyErrorDetector as LaravelConcurrencyErrorDetector;
use PDOException;
use Throwable;

/**
 * Errores de concurrencia por SQLSTATE (ADR-004): falla de serialización
 * (40001) y deadlock (40P01). La detección de Laravel reconoce el deadlock de
 * PostgreSQL solo por el texto "deadlock detected", que cambia con
 * lc_messages del servidor; esta lo reconoce por el código, en la excepción o
 * en cualquiera de sus causas (DeadlockException envuelve el error original
 * de una transacción anidada). El resto lo delega en la de Laravel.
 *
 * DB::transaction la usa para decidir si reintenta, y ProblemDetailsRenderer
 * para responder 503 CONCURRENCY_CONFLICT cuando se agotaron los intentos.
 * El agotamiento de lock_timeout (55P03) no es de concurrencia: no se
 * reintenta.
 */
final class PostgresConcurrencyErrorDetector implements ConcurrencyErrorDetector
{
    private const SQLSTATES = ['40001', '40P01'];

    /**
     * El respaldo se tipa con la clase concreta: con la interfaz, el
     * contenedor resolvería esta misma clase y entraría en recursión.
     */
    public function __construct(
        private readonly LaravelConcurrencyErrorDetector $fallback = new LaravelConcurrencyErrorDetector,
    ) {}

    public function causedByConcurrencyError(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof PDOException && in_array(self::sqlState($current), self::SQLSTATES, true)) {
                return true;
            }
        }

        return $this->fallback->causedByConcurrencyError($e);
    }

    private static function sqlState(PDOException $exception): string
    {
        $state = $exception->errorInfo[0] ?? null;

        return is_string($state) && $state !== '' ? $state : (string) $exception->getCode();
    }
}
