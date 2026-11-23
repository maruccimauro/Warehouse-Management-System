<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use Illuminate\Console\Command;

/**
 * Sincroniza roles y permisos con el catálogo en código. Se ejecuta después de
 * migrar, en cualquier entorno (el despliegue de E8 lo incluye).
 */
class SyncAuthorizationCommand extends Command
{
    protected $signature = 'wms:sync-authorization';

    protected $description = 'Sincroniza los roles y permisos con el catálogo definido en código';

    public function handle(SyncRolesAndPermissions $sync): int
    {
        $sync();

        $this->components->info(sprintf(
            '%d permisos y %d roles sincronizados.',
            count(Permission::cases()),
            count(Role::cases()),
        ));

        return self::SUCCESS;
    }
}
