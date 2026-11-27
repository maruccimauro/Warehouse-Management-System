<?php

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concurrency\ConcurrencyTestCase;
use Tests\Support\AuditedOperations;
use Tests\TestCase;

/*
| Las pruebas de Feature arrancan la aplicación con Tests\TestCase y corren
| contra PostgreSQL real (base wms_testing). Unit y Architecture no arrancan
| la aplicación. Concurrency usa la misma base con truncado y queda fuera de
| la suite por defecto (grupo concurrency).
|
| RefreshDatabase se aplica por directorio de módulo, solo donde las pruebas
| usan tablas: cada prueba corre dentro de una transacción que se revierte.
*/

pest()->extend(TestCase::class)->in('Feature');

// Concurrencia real (E3.14): procesos hijos y truncado en lugar de transacción.
pest()->extend(ConcurrencyTestCase::class)->group('concurrency')->in('Concurrency');

// Toda operación de inventario confirmada tiene su auditoría (INV-012): se
// verifica al final de cada prueba, antes de revertir su transacción.
pest()->use(RefreshDatabase::class)
    ->afterEach(fn () => AuditedOperations::assertAllAudited())
    ->in('Feature/Audit', 'Feature/Catalog', 'Feature/Database', 'Feature/Http', 'Feature/Identity', 'Feature/Inbox', 'Feature/Inventory', 'Feature/Warehouse');

/**
 * Exime a la prueba de la verificación de INV-012: solo para las que insertan
 * operaciones con DB::table para probar el esquema.
 */
function skipAuditedOperationsCheck(): void
{
    AuditedOperations::skip();
}

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
