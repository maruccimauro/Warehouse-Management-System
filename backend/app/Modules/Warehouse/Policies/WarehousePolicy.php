<?php

namespace App\Modules\Warehouse\Policies;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Auth\Access\Response;

/**
 * Depósitos: la consulta sigue el alcance por depósito (REG-005) y la
 * administración exige además warehouses.manage. Los listados se filtran con
 * Warehouse::visibleTo().
 */
final class WarehousePolicy
{
    public function __construct(private readonly WarehouseAccess $access) {}

    public function view(User $user, Warehouse $warehouse): Response
    {
        return $this->access->authorizeView($user, $warehouse->id);
    }

    public function create(User $user): Response
    {
        return $this->access->authorizePermission($user, Permission::WarehousesManage);
    }

    public function update(User $user, Warehouse $warehouse): Response
    {
        return $this->access->authorizeOperation($user, $warehouse->id, Permission::WarehousesManage);
    }
}
