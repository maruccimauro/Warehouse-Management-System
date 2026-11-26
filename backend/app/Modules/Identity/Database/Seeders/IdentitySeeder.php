<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use LogicException;

/**
 * Datos de desarrollo del módulo Identity: el administrador de desarrollo y
 * usuarios ficticios con nombre y apellido, cada uno con un rol. El email
 * sale del nombre (maria.martinez@wms.test) y el código de usuario es U más
 * el número de orden con seis dígitos (U000001), el formato que reciben las
 * filas previas a la columna username.
 *
 * Es idempotente: cada usuario se identifica por su email y solo se crea si
 * no existe, y un rol se asigna solo a quien no tiene ninguno, así el
 * entrypoint puede sembrar en cada arranque sin duplicar datos ni pisar una
 * contraseña o un rol cambiados a mano. Los depósitos asignados los agrega
 * WarehouseAssignmentSeeder, después de crear los depósitos.
 */
class IdentitySeeder extends Seeder
{
    /**
     * Personas ficticias, en orden: el número de orden da el código (U000001)
     * y la posición en los ciclos de roles y depósitos. wms.demo_users no
     * puede superar la cantidad de la lista.
     *
     * @var list<string>
     */
    public const DEMO_USER_NAMES = [
        'María Martínez',
        'Juan Pérez',
        'Lucía Fernández',
        'Martín Gómez',
        'Sofía Rodríguez',
        'Diego López',
        'Valentina Díaz',
        'Pablo Sánchez',
        'Camila Romero',
        'Nicolás Torres',
    ];

    /**
     * Roles de los usuarios ficticios, en ciclo: U000001 operador,
     * U000002 operador, U000003 supervisor, U000004 compras,
     * U000005 auditor, U000006 operador y así sucesivamente.
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
                'username' => config()->string('wms.dev_admin.username'),
                'name' => config()->string('wms.dev_admin.name'),
                'password' => config()->string('wms.dev_admin.password'),
            ],
        );

        $this->assignRoleIfMissing($admin, Role::Administrator);
    }

    /**
     * Cantidad configurada de usuarios ficticios, acotada por la lista.
     */
    public static function demoUserCount(): int
    {
        $count = config()->integer('wms.demo_users');

        if ($count > count(self::DEMO_USER_NAMES)) {
            throw new LogicException('wms.demo_users supera las '.count(self::DEMO_USER_NAMES).' personas de IdentitySeeder::DEMO_USER_NAMES.');
        }

        return max(0, $count);
    }

    /**
     * Email del usuario ficticio con ese número de orden, desde 1:
     * nombre.apellido sin tildes y en minúsculas (maria.martinez@wms.test).
     */
    public static function demoEmail(int $number): string
    {
        return Str::of(self::DEMO_USER_NAMES[$number - 1])->ascii()->lower()->replace(' ', '.')->append('@wms.test')->toString();
    }

    public static function demoUsername(int $number): string
    {
        return sprintf('U%06d', $number);
    }

    private function seedDemoUsers(): void
    {
        for ($number = 1; $number <= self::demoUserCount(); $number++) {
            $email = self::demoEmail($number);

            $user = User::query()->where('email', $email)->first()
                ?? User::factory()->create([
                    'email' => $email,
                    'username' => self::demoUsername($number),
                    'name' => self::DEMO_USER_NAMES[$number - 1],
                ]);

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
