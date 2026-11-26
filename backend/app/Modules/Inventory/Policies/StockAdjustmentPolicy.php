<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockAdjustment;
use Illuminate\Auth\Access\Response;

/**
 * Consulta y decisión de documentos de ajuste (E3.23, D13 del plan de E3).
 * Consultan quien ajusta (inventory.adjust) y quien aprueba
 * (inventory.adjust.approve), dentro de su alcance de consulta (REG-005).
 * Decidir posiciones exige inventory.adjust.approve y operar en el depósito
 * del documento: un documento no visible responde 404 y uno visible sin
 * operación o sin el permiso, 403. Que el creador no decida lo suyo es una
 * regla de negocio con código propio y la aplica la Action.
 */
final class StockAdjustmentPolicy
{
    private const VIEW_PERMISSIONS = [Permission::InventoryAdjust, Permission::InventoryAdjustApprove];

    public function __construct(private readonly WarehouseAccess $access) {}

    public function viewAny(User $user): Response
    {
        foreach (self::VIEW_PERMISSIONS as $permission) {
            if ($user->checkPermissionTo($permission->value)) {
                return Response::allow();
            }
        }

        return Response::deny(__('identity.permission_denied'));
    }

    public function view(User $user, StockAdjustment $adjustment): Response
    {
        $scope = $this->access->authorizeView($user, $adjustment->warehouse_id);

        return $scope->denied() ? $scope : $this->viewAny($user);
    }

    public function decide(User $user, StockAdjustment $adjustment): Response
    {
        return $this->access->authorizeOperation($user, $adjustment->warehouse_id, Permission::InventoryAdjustApprove);
    }
}
