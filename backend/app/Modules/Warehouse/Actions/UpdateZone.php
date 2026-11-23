<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Zone;
use InvalidArgumentException;

/**
 * Modificación de nombre y tipo de una zona. El código es la clave con la que
 * la identifican documentos y etiquetas, y no cambia. El tipo de una zona de
 * sistema lo fija su rol (RECEPCION, PACKING, DESPACHO) y tampoco cambia.
 */
final class UpdateZone
{
    public function __invoke(Zone $zone, ?string $name = null, ?ZoneType $type = null): Zone
    {
        if ($type !== null && $zone->is_system && $type !== $zone->type) {
            throw new InvalidArgumentException("La zona {$zone->code} es de sistema: su tipo no cambia.");
        }

        $zone->fill(array_filter(['name' => $name, 'type' => $type], fn (mixed $value): bool => $value !== null))->save();

        return $zone;
    }
}
