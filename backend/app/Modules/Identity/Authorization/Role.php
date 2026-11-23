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
    case Administrator = 'administrator';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
    case Purchasing = 'purchasing';
    case Auditor = 'auditor';

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Como SAP_ALL: configura el sistema y puede operar. Las reglas de
            // segregación (REG-001, INV-010, DM-08) se validan por documento y
            // también lo alcanzan.
            self::Administrator => Permission::cases(),
            self::Supervisor => [
                Permission::LocationsBlock,
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
                Permission::SuppliersManage,
                Permission::ProcurementCreate,
                Permission::ProcurementCancel,
            ],
            self::Auditor => [
                Permission::AuditView,
            ],
        };
    }
}
