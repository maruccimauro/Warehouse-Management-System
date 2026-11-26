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
 * depósito (DM-21) siguen el alcance por depósito (REG-005), como los datos
 * de centro con el campo WERKS, y cada uno tiene su responsable: el stock
 * mínimo, el supervisor del depósito; los umbrales de ajuste, el control
 * central.
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

    /**
     * Datos de los artículos en un depósito (DM-21): los consulta quien ve el
     * depósito; uno fuera del alcance responde 404 (REG-005).
     */
    public function viewWarehouseSettings(User $user, Warehouse $warehouse): Response
    {
        return $this->access->canView($user, $warehouse->id) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Stock mínimo: planificación del depósito, con su permiso y el alcance de
     * operación sobre el depósito.
     */
    public function updateMinStock(User $user, Product $product, Warehouse $warehouse): Response
    {
        return $this->access->authorizeOperation($user, $warehouse->id, Permission::CatalogMinStockManage);
    }

    /**
     * Umbrales de aprobación de ajustes (REG-024): control central, con su
     * permiso y sin exigir operar en el depósito; basta con verlo.
     */
    public function updateAdjustmentThresholds(User $user, Product $product, Warehouse $warehouse): Response
    {
        $visible = $this->viewWarehouseSettings($user, $warehouse);

        return $visible->denied() ? $visible : $this->access->authorizePermission($user, Permission::InventoryThresholdsManage);
    }
}
