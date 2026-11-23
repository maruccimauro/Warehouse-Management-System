<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de desarrollo del módulo Identity: el administrador de desarrollo y
 * usuarios ficticios generados con la factory, cada uno con un rol.
 *
 * Es idempotente: cada usuario se identifica por su email y solo se crea si
 * no existe, y un rol se asigna solo a quien no tiene ninguno, así el
 * entrypoint puede sembrar en cada arranque sin duplicar datos ni pisar una
 * contraseña o un rol cambiados a mano. Los depósitos asignados se suman con
 * el módulo Warehouse (E2.08).
 */
class IdentitySeeder extends Seeder
{
    public const DEMO_EMAIL_PATTERN = 'usuario%02d@wms.test';

    /**
     * Roles de los usuarios ficticios, en ciclo: usuario01 operador,
     * usuario02 operador, usuario03 supervisor, usuario04 compras,
     * usuario05 auditor, usuario06 operador y así sucesivamente.
     */
    public const DEMO_ROLE_CYCLE = [Role::Operator, Role::Operator, Role::Supervisor, Role::Purchasing, Role::Auditor];

    public function __construct(private readonly SyncRolesAndPermissions $syncRolesAndPermissions) {}

    public function run(): void
    {
        ($this->syncRolesAndPermissions)();

        $this->seedDevelopmentAdmin();
        $this->seedDemoUsers();
    }

    private function seedDevelopmentAdmin(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => mb_strtolower(config()->string('wms.dev_admin.email'))],
            [
                'name' => config()->string('wms.dev_admin.name'),
                'password' => config()->string('wms.dev_admin.password'),
            ],
        );

        $this->assignRoleIfMissing($admin, Role::Administrator);
    }

    private function seedDemoUsers(): void
    {
        for ($number = 1; $number <= config()->integer('wms.demo_users'); $number++) {
            $email = sprintf(self::DEMO_EMAIL_PATTERN, $number);

            $user = User::query()->where('email', $email)->first()
                ?? User::factory()->create(['email' => $email]);

            $this->assignRoleIfMissing($user, self::DEMO_ROLE_CYCLE[($number - 1) % count(self::DEMO_ROLE_CYCLE)]);
        }
    }

    private function assignRoleIfMissing(User $user, Role $role): void
    {
        if ($user->roles()->doesntExist()) {
            $user->assignRole($role->value);
        }
    }
}
