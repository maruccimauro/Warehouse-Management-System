<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\InvalidStateTransition;
use Illuminate\Support\Facades\DB;

/**
 * Desbloqueo de una ubicación (domain-model.md, máquina de estados de
 * Location; INV-012). Solo desde blocked, con FOR UPDATE como BlockLocation;
 * desde otro estado, 409 INVALID_STATE_TRANSITION. En un depósito inactivo,
 * 422 WAREHOUSE_INACTIVE (REG-015): se reactiva el depósito y después se
 * desbloquea. El motivo del bloqueo se limpia de la ubicación y queda en el
 * registro de auditoría.
 */
final class UnblockLocation
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LockActiveWarehouse $lockActiveWarehouse,
    ) {}

    public function __invoke(Location $location): Location
    {
        return DB::transaction(function () use ($location): Location {
            ($this->lockActiveWarehouse)($location->warehouse_id);
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            if ($location->status !== LocationStatus::Blocked) {
                throw new InvalidStateTransition($location->status, LocationStatus::Active);
            }

            $before = self::snapshot($location);

            $location->forceFill(['status' => LocationStatus::Active, 'status_reason' => null])->save();

            $this->audit->record('location.unblocked', $location, $location->warehouse_id, $before, self::snapshot($location));

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
