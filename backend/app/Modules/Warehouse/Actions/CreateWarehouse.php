<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Warehouse\Enums\SystemLocationRole;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Crea un depósito con sus zonas y ubicaciones de sistema (RECEPCION, PACKING
 * y DESPACHO) en una sola transacción: un depósito sin ellas no podría
 * recibir ni despachar (E2.02).
 */
final class CreateWarehouse
{
    public function __invoke(string $code, string $name, ?string $address = null): Warehouse
    {
        return DB::transaction(function () use ($code, $name, $address): Warehouse {
            $warehouse = Warehouse::query()->create([
                'code' => $code,
                'name' => $name,
                'address' => $address,
            ]);

            foreach (SystemLocationRole::cases() as $role) {
                $zone = $warehouse->zones()->create([
                    'code' => $role->code(),
                    'name' => $role->zoneName(),
                    'type' => $role->zoneType(),
                    'is_system' => true,
                ]);

                $warehouse->locations()->create([
                    'zone_id' => $zone->id,
                    'code' => $role->code(),
                    'system_role' => $role,
                ]);
            }

            return $warehouse;
        });
    }
}
