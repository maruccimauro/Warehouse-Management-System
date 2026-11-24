<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Auth\Access\Response;

/**
 * Categorías, unidades de medida y productos. El catálogo es global, como los
 * datos generales del material en SAP: se consulta con la sesión, sin permiso
 * propio, y se administra con catalog.manage. Los datos de un producto en un
 * depósito (DM-21) siguen además el alcance por depósito (REG-005), como los
 * datos de centro con el campo WERKS.
 */
final class CatalogPolicy
{
    public function __construct(private readonly WarehouseAccess $access) {}

    public function view(User $user): Response
    {
        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->access->authorizePermission($user, Permission::CatalogManage);
    }

    public function update(User $user): Response
    {
        return $this->access->authorizePermission($user, Permission::CatalogManage);
    }

    public function updateWarehouseSettings(User $user, Product $product, Warehouse $warehouse): Response
    {
        return $this->access->authorizeOperation($user, $warehouse->id, Permission::CatalogManage);
    }
}
