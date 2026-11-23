<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;

/**
 * Alta de una zona de usuario. Las zonas de sistema solo las crea
 * CreateWarehouse.
 */
final class CreateZone
{
    public function __invoke(Warehouse $warehouse, string $code, string $name, ZoneType $type): Zone
    {
        return $warehouse->zones()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_system' => false,
        ]);
    }
}
