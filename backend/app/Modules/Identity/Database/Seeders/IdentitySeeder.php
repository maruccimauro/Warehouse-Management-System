<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de desarrollo del módulo Identity: el administrador de desarrollo y
 * usuarios ficticios generados con la factory.
 *
 * Es idempotente: cada usuario se identifica por su email y solo se crea si
 * no existe, así el entrypoint puede sembrar en cada arranque sin duplicar
 * datos ni pisar una contraseña cambiada a mano. Roles, permisos y depósitos
 * asignados se suman en E2, cuando existan sus tablas.
 */
class IdentitySeeder extends Seeder
{
    public const DEMO_EMAIL_PATTERN = 'usuario%02d@wms.test';

    public function run(): void
    {
        $this->seedDevelopmentAdmin();
        $this->seedDemoUsers();
    }

    private function seedDevelopmentAdmin(): void
    {
        User::query()->firstOrCreate(
            ['email' => mb_strtolower(config()->string('wms.dev_admin.email'))],
            [
                'name' => config()->string('wms.dev_admin.name'),
                'password' => config()->string('wms.dev_admin.password'),
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedDemoUsers(): void
    {
        for ($number = 1; $number <= config()->integer('wms.demo_users'); $number++) {
            $email = sprintf(self::DEMO_EMAIL_PATTERN, $number);

            if (User::query()->where('email', $email)->doesntExist()) {
                User::factory()->create(['email' => $email]);
            }
        }
    }
}
