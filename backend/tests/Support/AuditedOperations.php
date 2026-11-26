<?php

namespace Tests\Support;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Audit\Services\DatabaseAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * INV-012 en toda la suite: al terminar cada prueba con base de datos, y antes
 * de que RefreshDatabase revierta su transacción, toda operación de inventario
 * tiene su registro inventory_operation.created en audit_logs.
 *
 * No aplica cuando la prueba reemplaza el contrato (RecordingAuditLogger) ni
 * cuando inserta operaciones con DB::table para probar el esquema; esas
 * pruebas lo declaran con skipAuditedOperationsCheck().
 */
final class AuditedOperations
{
    private const IN_FAILED_SQL_TRANSACTION = '25P02';

    private static bool $skipped = false;

    public static function skip(): void
    {
        self::$skipped = true;
    }

    public static function assertAllAudited(): void
    {
        $skipped = self::$skipped;
        self::$skipped = false;

        if ($skipped || ! app(AuditLogger::class) instanceof DatabaseAuditLogger) {
            return;
        }

        try {
            $unaudited = DB::table('inventory_operations')
                ->whereNotExists(fn ($query) => $query->from('audit_logs')
                    ->where('audit_logs.action', 'inventory_operation.created')
                    ->where('audit_logs.auditable_type', 'inventory_operation')
                    ->whereColumn('audit_logs.auditable_id', 'inventory_operations.id')
                    ->whereColumn('audit_logs.operation_id', 'inventory_operations.id'))
                ->pluck('number')
                ->all();
        } catch (QueryException $exception) {
            // La prueba dejó la transacción abortada a propósito (un error de la
            // base esperado): nada de ella puede confirmarse, así que no hay
            // operación confirmada que verificar.
            if ($exception->getCode() === self::IN_FAILED_SQL_TRANSACTION) {
                return;
            }

            throw $exception;
        }

        Assert::assertSame([], $unaudited, 'Operaciones de inventario sin registro de auditoría (INV-012).');
    }
}
