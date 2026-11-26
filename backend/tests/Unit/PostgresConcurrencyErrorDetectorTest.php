<?php

use App\Shared\Database\PostgresConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;

/*
| Detector de errores de concurrencia por SQLSTATE (ADR-004): reconoce el
| deadlock y la falla de serialización con el mensaje en cualquier idioma.
*/

/**
 * Error de la base con su SQLSTATE y el mensaje del servidor.
 */
function databaseError(string $sqlState, string $message): QueryException
{
    $pdo = new PDOException($message);
    $pdo->errorInfo = [$sqlState, 7, $message];

    return new QueryException('pgsql', 'SELECT 1', [], $pdo);
}

it('recognizes a deadlock and a serialization failure whatever the server language', function (string $sqlState, string $message) {
    expect((new PostgresConcurrencyErrorDetector)->causedByConcurrencyError(databaseError($sqlState, $message)))->toBeTrue();
})->with([
    'deadlock in Spanish' => ['40P01', 'ERROR:  se ha detectado un bloqueo mutuo'],
    'deadlock in English' => ['40P01', 'ERROR:  deadlock detected'],
    'serialization failure in Spanish' => ['40001', 'ERROR:  no se pudo serializar el acceso debido a actualizaciones concurrentes'],
]);

it('recognizes the deadlock that a nested transaction wraps in DeadlockException', function () {
    $nested = new DeadlockException('ERROR:  se ha detectado un bloqueo mutuo', 0, databaseError('40P01', 'ERROR:  se ha detectado un bloqueo mutuo'));

    expect((new PostgresConcurrencyErrorDetector)->causedByConcurrencyError($nested))->toBeTrue();
});

it('does not retry lock timeouts, constraint violations or other errors', function (string $sqlState, string $message) {
    expect((new PostgresConcurrencyErrorDetector)->causedByConcurrencyError(databaseError($sqlState, $message)))->toBeFalse();
})->with([
    'lock_timeout' => ['55P03', 'ERROR:  canceling statement due to lock timeout'],
    'unique violation' => ['23505', 'ERROR:  duplicate key value violates unique constraint "products_sku_unique"'],
    'trigger exception' => ['23514', 'ERROR:  REVERSAL_EXCEEDS_REMAINING'],
]);
