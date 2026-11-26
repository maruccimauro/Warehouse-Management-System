<?php

namespace App\Modules\Identity\Authorization;

/**
 * Catálogo de permisos con nomenclatura modulo.accion (ADR-009).
 *
 * Es la fuente de verdad que el seeder lleva a la tabla permissions y que las
 * Policies de cada módulo citan. Refleja el catálogo de permisos de
 * docs/03-architecture/domain-model.md: un permiso nuevo se agrega en los dos
 * lugares en el mismo commit.
 */
enum Permission: string
{
    // Alcance por depósito (E2.08, REG-005). Como el campo WERKS de los
    // objetos de autorización de SAP con valor *: view_all equivale a la
    // actividad 03 (visualizar) y operate_all a 01 y 02 (crear y modificar).
    case WarehousesViewAll = 'warehouses.view_all';
    case WarehousesOperateAll = 'warehouses.operate_all';

    // Datos maestros (E2).
    case WarehousesManage = 'warehouses.manage';
    case LocationsManage = 'locations.manage';
    case LocationsBlock = 'locations.block';
    case CatalogManage = 'catalog.manage';
    // Stock mínimo de un artículo en un depósito (DM-21): planificación del
    // depósito, separada del maestro global (catalog.manage).
    case CatalogMinStockManage = 'catalog.min_stock.manage';
    case SuppliersManage = 'suppliers.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    // Inventario (E3).
    case InventoryTransfer = 'inventory.transfer';
    case InventoryAdjust = 'inventory.adjust';
    case InventoryAdjustApprove = 'inventory.adjust.approve';
    case InventoryBlock = 'inventory.block';
    case InventoryReverse = 'inventory.reverse';
    // Carga inicial de stock (561, E3.06): no pasa por el umbral de
    // aprobación, así que el permiso es el control.
    case InventoryInitialLoad = 'inventory.initial_load';
    // Umbrales de aprobación de ajustes por artículo y depósito (REG-024): es
    // un control central, separado de quien ajusta y de quien aprueba.
    case InventoryThresholdsManage = 'inventory.thresholds.manage';

    // Abastecimiento y recepción (E4).
    case ProcurementCreate = 'procurement.create';
    case ProcurementApprove = 'procurement.approve';
    case ProcurementCancel = 'procurement.cancel';
    case ProcurementClose = 'procurement.close';
    case ReceivingCreate = 'receiving.create';
    case ReceivingBlind = 'receiving.blind';
    case ReceivingConfirm = 'receiving.confirm';
    case ReceivingCancel = 'receiving.cancel';
    case ReceivingOverReceive = 'receiving.over_receive';
    case ReceivingReverse = 'receiving.reverse';
    case PutawayExecute = 'putaway.execute';
    case PutawayReverse = 'putaway.reverse';

    // Pedidos y despacho (E5).
    case OrdersCreate = 'orders.create';
    case OrdersReserve = 'orders.reserve';
    case OrdersCancel = 'orders.cancel';
    case PickingManage = 'picking.manage';
    case PickingExecute = 'picking.execute';
    case PickingReverse = 'picking.reverse';
    case PackingExecute = 'packing.execute';
    case PackingReverse = 'packing.reverse';
    case DispatchExecute = 'dispatch.execute';
    case DispatchReverse = 'dispatch.reverse';

    // Inventario físico (E6).
    case StocktakeManage = 'stocktake.manage';
    case StocktakeCount = 'stocktake.count';
    case StocktakeApprove = 'stocktake.approve';
    case StocktakeReverse = 'stocktake.reverse';

    // Auditoría.
    case AuditView = 'audit.view';
}
