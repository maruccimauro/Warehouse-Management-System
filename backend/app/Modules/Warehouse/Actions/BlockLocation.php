<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\InvalidStateTransition;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueo de una ubicación con motivo (domain-model.md, máquina de estados de
 * Location; INV-012). Equivale al bloqueo de ubicación de SAP WM en LS02N:
 * mientras está bloqueada no admite movimientos (INV-009).
 *
 * - Solo desde active. Desde blocked, counting o inactive, 409
 *   INVALID_STATE_TRANSITION: no se bloquea una ubicación en conteo.
 * - Las ubicaciones de sistema también se bloquean (por ejemplo, un muelle de
 *   recepción fuera de servicio); lo que no admiten es la desactivación.
 * - El motivo queda en status_reason; la base lo exige con
 *   locations_blocked_reason_check.
 *
 * Es una operación del depósito: si está inactivo, 422 WAREHOUSE_INACTIVE
 * (REG-015). El depósito se bloquea primero con FOR SHARE (ADR-003, nivel 0).
 *
 * La fila se bloquea con FOR UPDATE y el estado se valida después de obtener
 * el bloqueo, para que un bloqueo y una desactivación o un inicio de
 * inventario simultáneos no partan del mismo estado.
 */
final class BlockLocation
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LockActiveWarehouse $lockActiveWarehouse,
    ) {}

    public function __invoke(Location $location, string $reason): Location
    {
        return DB::transaction(function () use ($location, $reason): Location {
            ($this->lockActiveWarehouse)($location->warehouse_id);
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            if ($location->status !== LocationStatus::Active) {
                throw new InvalidStateTransition($location->status, LocationStatus::Blocked);
            }

            $before = self::snapshot($location);

            $location->forceFill(['status' => LocationStatus::Blocked, 'status_reason' => $reason])->save();

            $this->audit->record('location.blocked', $location, $location->warehouse_id, $before, self::snapshot($location));

            return $location;
        });
    }

    /**
     * @return array{status: string, status_reason: string|null}
     */
    private static function snapshot(Location $location): array
    {
        return ['status' => $location->status->value, 'status_reason' => $location->status_reason];
    }
}
