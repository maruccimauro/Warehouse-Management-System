<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Depósitos asignados a los usuarios ficticios (REG-005). Corre después de
 * IdentitySeeder y WarehouseSeeder.
 *
 * El administrador no recibe asignaciones: opera en todos con
 * warehouses.operate_all. Los usuarios ficticios reciben depósitos en ciclo,
 * para que haya quien opere en uno solo y vea el otro sin poder operar:
 * usuario01 CENTRAL, usuario02 NORTE, usuario03 los dos, y así sucesivamente.
 *
 * Es idempotente: solo asigna a quien no tiene ningún depósito, así no pisa
 * una asignación cambiada a mano.
 */
class WarehouseAssignmentSeeder extends Seeder
{
    /** @var list<list<string>> */
    public const DEMO_WAREHOUSE_CYCLE = [['CENTRAL'], ['NORTE'], ['CENTRAL', 'NORTE']];

    public function run(): void
    {
        $warehouseIds = Warehouse::query()->pluck('id', 'code');

        for ($number = 1; $number <= config()->integer('wms.demo_users'); $number++) {
            $user = User::query()->where('email', sprintf(IdentitySeeder::DEMO_EMAIL_PATTERN, $number))->first();

            if ($user === null || $user->warehouses()->exists()) {
                continue;
            }

            $codes = self::DEMO_WAREHOUSE_CYCLE[($number - 1) % count(self::DEMO_WAREHOUSE_CYCLE)];
            $user->warehouses()->attach($warehouseIds->only($codes)->values()->all());
        }
    }
}
