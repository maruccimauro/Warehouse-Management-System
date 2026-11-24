<?php

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Las pruebas de Feature arrancan la aplicación con Tests\TestCase y corren
| contra PostgreSQL real (base wms_testing). Unit y Architecture no arrancan
| la aplicación.
|
| RefreshDatabase se aplica por directorio de módulo, solo donde las pruebas
| usan tablas: cada prueba corre dentro de una transacción que se revierte.
*/

pest()->extend(TestCase::class)->in('Feature');

pest()->use(RefreshDatabase::class)->in('Feature/Catalog', 'Feature/Database', 'Feature/Http', 'Feature/Identity', 'Feature/Warehouse');

/**
 * Usuario con un rol (null: sin rol, como uno sin warehouses.view_all) y los
 * depósitos asignados. Requiere los roles sincronizados con
 * SyncRolesAndPermissions.
 *
 * @param  list<Warehouse>  $warehouses
 */
function userWithAccess(?Role $role, array $warehouses = []): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        $user->assignRole($role->value);
    }

    $user->warehouses()->attach(array_map(fn (Warehouse $warehouse): int => $warehouse->id, $warehouses));

    return $user;
}
