<?php

namespace App\Modules\Warehouse\Policies;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Auth\Access\Response;

/**
 * Ubicaciones: consulta por alcance del depósito (REG-005); alta y edición con
 * locations.manage, bloqueo y desbloqueo con locations.block, siempre en un
 * depósito donde el usuario opera.
 */
final class LocationPolicy
{
    public function __construct(private readonly WarehouseAccess $access) {}

    public function view(User $user, Location $location): Response
    {
        return $this->access->authorizeView($user, $location->warehouse_id);
    }

    public function create(User $user, Warehouse $warehouse): Response
    {
        return $this->access->authorizeOperation($user, $warehouse->id, Permission::LocationsManage);
    }

    public function update(User $user, Location $location): Response
    {
        return $this->access->authorizeOperation($user, $location->warehouse_id, Permission::LocationsManage);
    }

    public function block(User $user, Location $location): Response
    {
        return $this->access->authorizeOperation($user, $location->warehouse_id, Permission::LocationsBlock);
    }

    public function unblock(User $user, Location $location): Response
    {
        return $this->block($user, $location);
    }
}
