<?php

namespace App\Modules\Audit\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría de una operación crítica (INV-012, ADR-008).
 *
 * Las Actions lo invocan dentro de la misma transacción del cambio, después de
 * aplicarlo: si la transacción se revierte, el registro se revierte con ella.
 * La implementación completa los datos del contexto (usuario, IP, request_id)
 * y el tipo de la entidad con el alias del morph map.
 */
interface AuditLogger
{
    /**
     * @param  string  $action  Acción en la forma entidad.verbo, por ejemplo location.blocked
     * @param  array<string, mixed>|null  $before  Valores afectados antes del cambio
     * @param  array<string, mixed>|null  $after  Valores afectados después del cambio
     * @param  int|null  $operationId  Operación de inventario que generó el cambio, si la hay:
     *                                 la propia operación o la que contabiliza un documento (E4 en adelante)
     */
    public function record(string $action, Model $auditable, ?int $warehouseId, ?array $before, ?array $after, ?int $operationId = null): void;
}
