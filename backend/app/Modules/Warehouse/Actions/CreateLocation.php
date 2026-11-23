<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Zone;
use InvalidArgumentException;

/**
 * Alta de una ubicación de usuario en una zona que no es de sistema. Las
 * ubicaciones de sistema las crea CreateWarehouse, como las del área
 * intermedia de SAP, que no se dan de alta a mano.
 */
final class CreateLocation
{
    /**
     * @param  array{aisle?: string|null, rack?: string|null, level?: string|null, position?: string|null, max_units?: int|null}  $attributes
     */
    public function __invoke(Zone $zone, string $code, array $attributes = []): Location
    {
        if ($zone->is_system) {
            throw new InvalidArgumentException("La zona {$zone->code} es de sistema: sus ubicaciones las crea el depósito.");
        }

        $location = $zone->locations()->create([
            ...array_intersect_key($attributes, array_flip(['aisle', 'rack', 'level', 'position', 'max_units'])),
            'warehouse_id' => $zone->warehouse_id,
            'code' => $code,
        ]);

        return $location->refresh();
    }
}
