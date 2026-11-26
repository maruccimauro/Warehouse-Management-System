<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Audit\Models\AuditLog;
use App\Shared\Http\RequestContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use LogicException;

/**
 * Escribe la auditoría en audit_logs (INV-012, ADR-008), en la transacción
 * de quien la invoca: no abre una propia, de modo que un rollback del cambio
 * también descarta su registro.
 *
 * El usuario sale de la guardia vigente y la IP y el identificador de
 * petición del contexto que carga AssignRequestId. Fuera de una petición HTTP
 * (comandos, seeders, programador) los tres quedan nulos.
 */
final readonly class DatabaseAuditLogger implements AuditLogger
{
    public function __construct(private AuthFactory $auth) {}

    public function record(string $action, Model $auditable, ?int $warehouseId, ?array $before, ?array $after, ?int $operationId = null): void
    {
        $key = $auditable->getKey();

        if (! is_int($key)) {
            throw new LogicException("No se puede auditar {$action}: la entidad no tiene un id entero persistido.");
        }

        $userId = $this->auth->guard()->id();
        $ip = Context::getHidden(RequestContext::IP);
        $requestId = Context::get(RequestContext::REQUEST_ID);

        AuditLog::query()->create([
            'user_id' => is_int($userId) ? $userId : null,
            'warehouse_id' => $warehouseId,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $key,
            'before' => $before,
            'after' => $after,
            'operation_id' => $operationId,
            'ip' => is_string($ip) ? $ip : null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]);
    }
}
