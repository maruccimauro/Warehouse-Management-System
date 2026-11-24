<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Proveedores y su relación con los productos (DM-22). Son datos maestros
 * globales, como el catálogo: se consultan con la sesión, porque recepción
 * muestra el proveedor de la orden de compra, y se administran con
 * suppliers.manage, que tiene el rol Compras.
 */
final class SupplierPolicy
{
    public function __construct(private readonly WarehouseAccess $access) {}

    public function view(User $user): Response
    {
        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->access->authorizePermission($user, Permission::SuppliersManage);
    }

    public function update(User $user): Response
    {
        return $this->access->authorizePermission($user, Permission::SuppliersManage);
    }
}
