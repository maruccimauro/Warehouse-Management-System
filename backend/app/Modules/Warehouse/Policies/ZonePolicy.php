<?php

namespace App\Modules\Warehouse\Policies;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Auth\Access\Response;

/**
 * Zonas: consulta por alcance del depósito (REG-005); alta y edición con
 * locations.manage en un depósito donde el usuario opera.
 */
final class ZonePolicy
{
    public function __construct(private readonly WarehouseAccess $access) {}

    public function view(User $user, Zone $zone): Response
    {
        return $this->access->authorizeView($user, $zone->warehouse_id);
    }

    public function create(User $user, Warehouse $warehouse): Response
    {
        return $this->access->authorizeOperation($user, $warehouse->id, Permission::LocationsManage);
    }

    public function update(User $user, Zone $zone): Response
    {
        return $this->access->authorizeOperation($user, $zone->warehouse_id, Permission::LocationsManage);
    }
}
