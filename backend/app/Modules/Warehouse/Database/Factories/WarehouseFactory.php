<?php

namespace App\Modules\Warehouse\Database\Factories;

use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Depósito sin zonas ni ubicaciones de sistema. Para un depósito completo,
 * usar la Action CreateWarehouse.
 *
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('DEP-###'),
            'name' => 'Depósito '.fake()->city(),
            'address' => fake()->address(),
        ];
    }
}
