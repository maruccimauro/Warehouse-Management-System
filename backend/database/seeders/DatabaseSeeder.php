<?php

namespace Database\Seeders;

use App\Modules\Identity\Database\Seeders\IdentitySeeder;
use App\Modules\Identity\Database\Seeders\WarehouseAssignmentSeeder;
use App\Modules\Warehouse\Database\Seeders\WarehouseSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Orquesta los seeders de desarrollo de cada módulo, en orden de dependencia.
 * Cada etapa agrega el de su módulo.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Entornos donde se permiten datos de desarrollo con credenciales públicas. */
    public const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    public function run(): void
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw new RuntimeException(sprintf(
                'Los seeders de desarrollo solo corren en %s; el entorno actual es %s.',
                implode(' y ', self::ALLOWED_ENVIRONMENTS),
                app()->environment(),
            ));
        }

        $this->call([
            IdentitySeeder::class,
            WarehouseSeeder::class,
            WarehouseAssignmentSeeder::class,
        ]);
    }
}
