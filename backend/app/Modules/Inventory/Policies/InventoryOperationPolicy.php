<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Operaciones de inventario manuales (E3.04 en adelante). El depósito llega en
 * el cuerpo de la petición, no en la ruta: se exige primero el permiso y
 * después que el usuario opere en el depósito (REG-005). Un depósito que no
 * existe o que el usuario no ve responde 403 igual que uno visible y no
 * asignado, para no revelar cuáles existen. Sin depósito válido en el cuerpo
 * decide solo el permiso, y la validación responde 422.
 */
final class InventoryOperationPolicy
{
    public function __construct(private readonly WarehouseAccess $access) {}

    public function transfer(User $user, ?int $warehouseId): Response
    {
        return $this->operate($user, $warehouseId, Permission::InventoryTransfer);
    }

    private function operate(User $user, ?int $warehouseId, Permission $permission): Response
    {
        $response = $this->access->authorizePermission($user, $permission);

        if ($response->denied() || $warehouseId === null || $this->access->canOperate($user, $warehouseId)) {
            return $response;
        }

        return Response::deny(__('identity.warehouse_not_assigned'));
    }
}
