<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Zone;
use App\Shared\Exceptions\InvalidStateTransition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Modificación de zona, coordenadas, capacidad y activación de una ubicación
 * (domain-model.md, máquina de estados de Location).
 *
 * - El código no cambia: es la clave que se escanea en la operación.
 * - Solo se pasa de active a inactive y de inactive a active. blocked y
 *   counting tienen sus propias operaciones (bloqueo en E2.04, inventario
 *   físico en E6): desde ellos, 409 INVALID_STATE_TRANSITION.
 * - Una ubicación de sistema no se desactiva ni cambia de zona; la base lo
 *   respalda con locations_system_not_inactive_check.
 * - Una ubicación de usuario no pasa a una zona de sistema ni a otro depósito.
 *
 * La fila se bloquea con FOR UPDATE para que una desactivación y un bloqueo
 * simultáneos no partan del mismo estado. Desactivar exige además no tener
 * stock físico: se valida en E3, cuando exista la tabla stocks.
 */
final class UpdateLocation
{
    private const EDITABLE = ['aisle', 'rack', 'level', 'position', 'max_units'];

    private const TOGGLEABLE = [LocationStatus::Active, LocationStatus::Inactive];

    /**
     * @param  array{aisle?: string|null, rack?: string|null, level?: string|null, position?: string|null, max_units?: int|null}  $attributes
     */
    public function __invoke(Location $location, array $attributes = [], ?Zone $zone = null, ?LocationStatus $status = null): Location
    {
        return DB::transaction(function () use ($location, $attributes, $zone, $status): Location {
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            if ($zone !== null && $zone->id !== $location->zone_id) {
                $this->assertCanMoveTo($location, $zone);
                $location->zone_id = $zone->id;
            }

            if ($status !== null && $status !== $location->status) {
                $this->assertCanChangeStatus($location, $status);
                $location->status = $status;
            }

            $location->fill(array_intersect_key($attributes, array_flip(self::EDITABLE)))->save();

            return $location;
        });
    }

    private function assertCanMoveTo(Location $location, Zone $zone): void
    {
        if ($location->is_system) {
            throw new InvalidArgumentException("La ubicación {$location->code} es de sistema: no cambia de zona.");
        }

        if ($zone->is_system || $zone->warehouse_id !== $location->warehouse_id) {
            throw new InvalidArgumentException("La zona {$zone->code} no admite ubicaciones de usuario de este depósito.");
        }
    }

    private function assertCanChangeStatus(Location $location, LocationStatus $status): void
    {
        if ($location->is_system) {
            throw new InvalidArgumentException("La ubicación {$location->code} es de sistema: no se desactiva.");
        }

        if (! in_array($location->status, self::TOGGLEABLE, true) || ! in_array($status, self::TOGGLEABLE, true)) {
            throw new InvalidStateTransition($location->status, $status);
        }
    }
}
