<?php

namespace App\Modules\Identity\Authorization;

/**
 * Roles del sistema y sus permisos (E2.07).
 *
 * El valor es un identificador estable que guarda la tabla roles; el nombre
 * visible lo traduce el frontend, porque los roles son datos del sistema y no
 * de los usuarios. La asignación de permisos sigue la tabla de roles de
 * docs/03-architecture/domain-model.md.
 */
enum Role: string
{
    /**
     * Permisos de control de operaciones, comunes al Gerente y al Jefe de
     * operaciones; los distingue solo el alcance por depósito.
     */
    private const OPERATIONS_CONTROL = [
        Permission::WarehousesViewAll,
        Permission::InventoryAdjustApprove,
        Permission::InventoryThresholdsManage,
        Permission::InventoryInitialLoad,
        Permission::ProcurementApprove,
        Permission::StocktakeApprove,
        Permission::AuditView,
    ];

    case Administrator = 'administrator';
    case OperationsManager = 'operations_manager';
    case OperationsLead = 'operations_lead';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
    case Purchasing = 'purchasing';
    case Auditor = 'auditor';

    /**
     * Roles críticos (D16.4, D16.11): administran el sistema o controlan la
     * operación por encima de los supervisores. Asignarlos exige una
     * confirmación explícita y avisa a los Administradores (REG-025).
     *
     * @return list<self>
     */
    public static function critical(): array
    {
        return [self::Administrator, self::OperationsManager, self::OperationsLead];
    }

    public function isCritical(): bool
    {
        return in_array($this, self::critical(), true);
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Como SAP_ALL: configura el sistema y puede operar. Las reglas de
            // segregación (REG-001, INV-010, DM-08) se validan por documento y
            // también lo alcanzan. Todos los roles consultan los depósitos
            // (warehouses.view_all), para ubicar mercadería entre depósitos;
            // operar en todos sin asignación (warehouses.operate_all) es del
            // administrador y del gerente de operaciones.
            self::Administrator => Permission::cases(),
            // Control por encima de los supervisores sin la operación diaria:
            // quien fija los umbrales y aprueba no ajusta (regla de
            // responsabilidades por transacción). No administra usuarios,
            // roles, depósitos, catálogo ni proveedores (decisiones del
            // usuario del 26/11/2026, D16.7). El Gerente de operaciones actúa
            // en todos los depósitos; el Jefe de operaciones, en los que tiene
            // asignados.
            self::OperationsManager => [
                Permission::WarehousesOperateAll,
                ...self::OPERATIONS_CONTROL,
            ],
            self::OperationsLead => self::OPERATIONS_CONTROL,
            self::Supervisor => [
                Permission::WarehousesViewAll,
                // Zonas, ubicaciones y stock mínimo de los depósitos donde
                // opera: las Policies limitan por depósito de operación.
                Permission::LocationsManage,
                Permission::LocationsBlock,
                Permission::CatalogMinStockManage,
                Permission::InventoryTransfer,
                Permission::InventoryAdjust,
                Permission::InventoryAdjustApprove,
                Permission::InventoryBlock,
                Permission::InventoryReverse,
                Permission::ProcurementApprove,
                Permission::ProcurementCancel,
                Permission::ProcurementClose,
                Permission::ReceivingCreate,
                Permission::ReceivingBlind,
                Permission::ReceivingConfirm,
                Permission::ReceivingCancel,
                Permission::ReceivingOverReceive,
                Permission::ReceivingReverse,
                Permission::PutawayExecute,
                Permission::PutawayReverse,
                Permission::OrdersCreate,
                Permission::OrdersReserve,
                Permission::OrdersCancel,
                Permission::PickingManage,
                Permission::PickingExecute,
                Permission::PickingReverse,
                Permission::PackingExecute,
                Permission::PackingReverse,
                Permission::DispatchExecute,
                Permission::DispatchReverse,
                Permission::StocktakeManage,
                Permission::StocktakeApprove,
                Permission::StocktakeReverse,
                Permission::AuditView,
            ],
            self::Operator => [
                Permission::WarehousesViewAll,
                Permission::InventoryTransfer,
                Permission::ReceivingCreate,
                Permission::ReceivingConfirm,
                Permission::PutawayExecute,
                Permission::PutawayReverse,
                Permission::PickingExecute,
                Permission::PackingExecute,
                Permission::DispatchExecute,
                Permission::StocktakeCount,
            ],
            self::Purchasing => [
                Permission::WarehousesViewAll,
                Permission::SuppliersManage,
                Permission::ProcurementCreate,
                Permission::ProcurementCancel,
            ],
            // Revisa quién tiene qué acceso (SUIM, PFCG) sin poder cambiarlo.
            self::Auditor => [
                Permission::WarehousesViewAll,
                Permission::UsersView,
                Permission::AuditView,
            ],
        };
    }
}
