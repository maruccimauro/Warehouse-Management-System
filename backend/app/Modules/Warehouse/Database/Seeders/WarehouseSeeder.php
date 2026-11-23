<?php

namespace App\Modules\Warehouse\Database\Seeders;

use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Depósitos de desarrollo, creados con la misma Action que la API para que
 * tengan sus zonas y ubicaciones de sistema. Idempotente: un depósito se
 * identifica por su código y solo se crea si no existe.
 */
class WarehouseSeeder extends Seeder
{
    /** @var list<array{code: string, name: string, address: string}> */
    public const WAREHOUSES = [
        ['code' => 'CENTRAL', 'name' => 'Depósito central', 'address' => 'Av. Industrial 1200, Parque Industrial'],
        ['code' => 'NORTE', 'name' => 'Depósito norte', 'address' => 'Ruta 9 km 45, Zona Franca'],
    ];

    public function __construct(private readonly CreateWarehouse $createWarehouse) {}

    public function run(): void
    {
        foreach (self::WAREHOUSES as $warehouse) {
            if (Warehouse::query()->where('code', $warehouse['code'])->doesntExist()) {
                ($this->createWarehouse)($warehouse['code'], $warehouse['name'], $warehouse['address']);
            }
        }
    }
}
