<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Contracts\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Punto de extensión hasta E3: la tabla audit_logs y su trigger de
 * inmutabilidad llegan con el núcleo de inventario. Las Actions ya invocan el
 * contrato, de modo que E3 solo reemplaza este binding por la implementación
 * que escribe en la base.
 */
final class NullAuditLogger implements AuditLogger
{
    public function record(string $action, Model $auditable, ?int $warehouseId, ?array $before, ?array $after): void
    {
        //
    }
}
