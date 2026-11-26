<?php

namespace Tests\Support;

use App\Modules\Audit\Contracts\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Registra cada llamada con el nivel de transacción del momento, para probar
 * que la auditoría ocurre dentro de la transacción del cambio.
 */
final class RecordingAuditLogger implements AuditLogger
{
    /** @var list<array{action: string, auditable: Model, warehouse_id: int|null, before: array<string, mixed>|null, after: array<string, mixed>|null, operation_id: int|null, transaction_level: int}> */
    public array $records = [];

    public function record(string $action, Model $auditable, ?int $warehouseId, ?array $before, ?array $after, ?int $operationId = null): void
    {
        $this->records[] = [
            'action' => $action,
            'auditable' => $auditable,
            'warehouse_id' => $warehouseId,
            'before' => $before,
            'after' => $after,
            'operation_id' => $operationId,
            'transaction_level' => DB::transactionLevel(),
        ];
    }
}
