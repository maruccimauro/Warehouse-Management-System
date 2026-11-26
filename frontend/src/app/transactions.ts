import type { ParseKeys } from 'i18next';
import { lazy, type ComponentType, type LazyExoticComponent } from 'react';
import type { PermissionName } from '@/features/auth/permissions';
import type { HelpKey } from '@/shared/help/helpLinks';

// Registro de transacciones (ADR-016). Cada etapa agrega las suyas con el código de SAP
// cuando hay equivalente y el rango ZW para las propias. En el rango ZW, la familia ZW0N es
// la gestión con lista y ficha y ZWN1, ZWN2 y ZWN3 son sus acciones crear, modificar y
// visualizar, como el sufijo de actividad de SAP.
export interface TransactionDefinition {
  code: string;
  titleKey: ParseKeys<'transactions'>;
  // Permiso de negocio que exige abrirla: el mismo que el backend controla al operar, en
  // lugar de un permiso propio por transacción como S_TCODE de SAP. Así no existe la
  // combinación "abre la pantalla pero cada grabación responde 403". null: cualquier
  // usuario con sesión (datos propios y consultas sin permiso específico).
  permission: PermissionName | null;
  // Página de la wiki de la transacción (DM-11): F1 la abre cuando el campo con foco no
  // tiene ayuda propia. Las claves de sus campos son field.<código>.<campo>.
  helpKey: HelpKey;
  // Carga diferida: cada transacción es un chunk propio que se descarga al abrirla.
  component: LazyExoticComponent<ComponentType>;
}

export type MenuNode =
  | { kind: 'folder'; id: string; labelKey: ParseKeys<'transactions'>; children: readonly MenuNode[] }
  | { kind: 'transaction'; code: string };

export const transactions: readonly TransactionDefinition[] = [
  {
    code: 'ZW01',
    titleKey: 'titles.ZW01',
    permission: 'warehouses.manage',
    helpKey: 'transaction.ZW01',
    component: lazy(() =>
      import('@/features/warehouses/WarehousesTransaction').then((module) => ({ default: module.WarehousesTransaction })),
    ),
  },
  {
    code: 'ZW11',
    titleKey: 'titles.ZW11',
    permission: 'warehouses.manage',
    helpKey: 'transaction.ZW11',
    component: lazy(() => import('@/features/warehouses/WarehouseActivityTransactions').then((module) => ({ default: module.CreateWarehouseTransaction }))),
  },
  {
    code: 'ZW12',
    titleKey: 'titles.ZW12',
    permission: 'warehouses.manage',
    helpKey: 'transaction.ZW12',
    component: lazy(() => import('@/features/warehouses/WarehouseActivityTransactions').then((module) => ({ default: module.ChangeWarehouseTransaction }))),
  },
  {
    code: 'ZW13',
    titleKey: 'titles.ZW13',
    permission: null,
    helpKey: 'transaction.ZW13',
    component: lazy(() => import('@/features/warehouses/WarehouseActivityTransactions').then((module) => ({ default: module.DisplayWarehouseTransaction }))),
  },
  {
    code: 'ZW02',
    titleKey: 'titles.ZW02',
    permission: 'locations.manage',
    helpKey: 'transaction.ZW02',
    component: lazy(() => import('@/features/warehouses/ZonesTransaction').then((module) => ({ default: module.ZonesTransaction }))),
  },
  {
    code: 'ZW21',
    titleKey: 'titles.ZW21',
    permission: 'locations.manage',
    helpKey: 'transaction.ZW21',
    component: lazy(() => import('@/features/warehouses/ZoneActivityTransactions').then((module) => ({ default: module.CreateZoneTransaction }))),
  },
  {
    code: 'ZW22',
    titleKey: 'titles.ZW22',
    permission: 'locations.manage',
    helpKey: 'transaction.ZW22',
    component: lazy(() => import('@/features/warehouses/ZoneActivityTransactions').then((module) => ({ default: module.ChangeZoneTransaction }))),
  },
  {
    code: 'ZW23',
    titleKey: 'titles.ZW23',
    permission: null,
    helpKey: 'transaction.ZW23',
    component: lazy(() => import('@/features/warehouses/ZoneActivityTransactions').then((module) => ({ default: module.DisplayZoneTransaction }))),
  },
  {
    code: 'LS01N',
    titleKey: 'titles.LS01N',
    permission: 'locations.manage',
    helpKey: 'transaction.LS01N',
    component: lazy(() =>
      import('@/features/warehouses/LocationTransaction').then((module) => ({ default: module.CreateLocationTransaction })),
    ),
  },
  {
    code: 'LS02N',
    titleKey: 'titles.LS02N',
    permission: 'locations.manage',
    helpKey: 'transaction.LS02N',
    component: lazy(() =>
      import('@/features/warehouses/LocationTransaction').then((module) => ({ default: module.ChangeLocationTransaction })),
    ),
  },
  {
    code: 'LS03N',
    titleKey: 'titles.LS03N',
    // Consultar: cualquier usuario; el backend limita a los depósitos que ve (REG-005).
    permission: null,
    helpKey: 'transaction.LS03N',
    component: lazy(() =>
      import('@/features/warehouses/LocationTransaction').then((module) => ({ default: module.DisplayLocationTransaction })),
    ),
  },
  {
    code: 'LS10',
    titleKey: 'titles.LS10',
    permission: 'locations.manage',
    helpKey: 'transaction.LS10',
    component: lazy(() =>
      import('@/features/warehouses/GenerateLocationsTransaction').then((module) => ({
        default: module.GenerateLocationsTransaction,
      })),
    ),
  },
  {
    code: 'LX03',
    titleKey: 'titles.LX03',
    permission: null,
    helpKey: 'transaction.LX03',
    component: lazy(() =>
      import('@/features/warehouses/LocationListTransaction').then((module) => ({ default: module.LocationListTransaction })),
    ),
  },
  {
    code: 'ZW05',
    titleKey: 'titles.ZW05',
    permission: 'locations.block',
    helpKey: 'transaction.ZW05',
    component: lazy(() =>
      import('@/features/warehouses/LocationBlockTransaction').then((module) => ({ default: module.LocationBlockTransaction })),
    ),
  },
  // Datos del artículo en un depósito (DM-21), con responsables distintos: el stock mínimo,
  // el supervisor del depósito; los umbrales de ajuste, el control central (REG-024).
  {
    code: 'ZW04',
    titleKey: 'titles.ZW04',
    permission: 'catalog.min_stock.manage',
    helpKey: 'transaction.ZW04',
    component: lazy(() =>
      import('@/features/productSettings/ProductSettingTransactions').then((module) => ({ default: module.MinStockManagementTransaction })),
    ),
  },
  {
    code: 'ZW42',
    titleKey: 'titles.ZW42',
    permission: 'catalog.min_stock.manage',
    helpKey: 'transaction.ZW42',
    component: lazy(() =>
      import('@/features/productSettings/ProductSettingTransactions').then((module) => ({ default: module.ChangeMinStockTransaction })),
    ),
  },
  {
    code: 'ZW43',
    titleKey: 'titles.ZW43',
    permission: null,
    helpKey: 'transaction.ZW43',
    component: lazy(() =>
      import('@/features/productSettings/ProductSettingTransactions').then((module) => ({ default: module.DisplayMinStockTransaction })),
    ),
  },
  {
    code: 'ZW35',
    titleKey: 'titles.ZW35',
    permission: 'inventory.thresholds.manage',
    helpKey: 'transaction.ZW35',
    component: lazy(() =>
      import('@/features/productSettings/ProductSettingTransactions').then((module) => ({ default: module.AdjustmentThresholdsManagementTransaction })),
    ),
  },
  {
    code: 'ZW36',
    titleKey: 'titles.ZW36',
    permission: 'inventory.thresholds.manage',
    helpKey: 'transaction.ZW36',
    component: lazy(() =>
      import('@/features/productSettings/ProductSettingTransactions').then((module) => ({ default: module.ChangeAdjustmentThresholdsTransaction })),
    ),
  },
  {
    code: 'ZW37',
    titleKey: 'titles.ZW37',
    permission: null,
    helpKey: 'transaction.ZW37',
    component: lazy(() =>
      import('@/features/productSettings/ProductSettingTransactions').then((module) => ({ default: module.DisplayAdjustmentThresholdsTransaction })),
    ),
  },
  {
    code: 'MM01',
    titleKey: 'titles.MM01',
    permission: 'catalog.manage',
    helpKey: 'transaction.MM01',
    component: lazy(() => import('@/features/catalog/ProductTransaction').then((module) => ({ default: module.CreateProductTransaction }))),
  },
  {
    code: 'MM02',
    titleKey: 'titles.MM02',
    permission: 'catalog.manage',
    helpKey: 'transaction.MM02',
    component: lazy(() => import('@/features/catalog/ProductTransaction').then((module) => ({ default: module.ChangeProductTransaction }))),
  },
  {
    code: 'MM03',
    titleKey: 'titles.MM03',
    // Consultar: el catálogo es global, alcanza con la sesión.
    permission: null,
    helpKey: 'transaction.MM03',
    component: lazy(() => import('@/features/catalog/ProductTransaction').then((module) => ({ default: module.DisplayProductTransaction }))),
  },
  {
    code: 'MM60',
    titleKey: 'titles.MM60',
    permission: null,
    helpKey: 'transaction.MM60',
    component: lazy(() => import('@/features/catalog/ProductListTransaction').then((module) => ({ default: module.ProductListTransaction }))),
  },
  {
    code: 'ZW07',
    titleKey: 'titles.ZW07',
    permission: 'catalog.manage',
    helpKey: 'transaction.ZW07',
    component: lazy(() => import('@/features/catalog/CategoriesTransaction').then((module) => ({ default: module.CategoriesTransaction }))),
  },
  {
    code: 'ZW71',
    titleKey: 'titles.ZW71',
    permission: 'catalog.manage',
    helpKey: 'transaction.ZW71',
    component: lazy(() => import('@/features/catalog/CategoryActivityTransactions').then((module) => ({ default: module.CreateCategoryTransaction }))),
  },
  {
    code: 'ZW72',
    titleKey: 'titles.ZW72',
    permission: 'catalog.manage',
    helpKey: 'transaction.ZW72',
    component: lazy(() => import('@/features/catalog/CategoryActivityTransactions').then((module) => ({ default: module.ChangeCategoryTransaction }))),
  },
  {
    code: 'ZW73',
    titleKey: 'titles.ZW73',
    permission: null,
    helpKey: 'transaction.ZW73',
    component: lazy(() => import('@/features/catalog/CategoryActivityTransactions').then((module) => ({ default: module.DisplayCategoryTransaction }))),
  },
  {
    code: 'ZW08',
    titleKey: 'titles.ZW08',
    permission: 'catalog.manage',
    helpKey: 'transaction.ZW08',
    component: lazy(() => import('@/features/catalog/UnitsTransaction').then((module) => ({ default: module.UnitsTransaction }))),
  },
  {
    code: 'ZW81',
    titleKey: 'titles.ZW81',
    permission: 'catalog.manage',
    helpKey: 'transaction.ZW81',
    component: lazy(() => import('@/features/catalog/UnitActivityTransactions').then((module) => ({ default: module.CreateUnitTransaction }))),
  },
  {
    code: 'ZW82',
    titleKey: 'titles.ZW82',
    permission: 'catalog.manage',
    helpKey: 'transaction.ZW82',
    component: lazy(() => import('@/features/catalog/UnitActivityTransactions').then((module) => ({ default: module.ChangeUnitTransaction }))),
  },
  {
    code: 'ZW83',
    titleKey: 'titles.ZW83',
    permission: null,
    helpKey: 'transaction.ZW83',
    component: lazy(() => import('@/features/catalog/UnitActivityTransactions').then((module) => ({ default: module.DisplayUnitTransaction }))),
  },
  {
    code: 'XK01',
    titleKey: 'titles.XK01',
    permission: 'suppliers.manage',
    helpKey: 'transaction.XK01',
    component: lazy(() => import('@/features/catalog/SupplierTransaction').then((module) => ({ default: module.CreateSupplierTransaction }))),
  },
  {
    code: 'XK02',
    titleKey: 'titles.XK02',
    permission: 'suppliers.manage',
    helpKey: 'transaction.XK02',
    component: lazy(() => import('@/features/catalog/SupplierTransaction').then((module) => ({ default: module.ChangeSupplierTransaction }))),
  },
  {
    code: 'XK03',
    titleKey: 'titles.XK03',
    permission: null,
    helpKey: 'transaction.XK03',
    component: lazy(() => import('@/features/catalog/SupplierTransaction').then((module) => ({ default: module.DisplaySupplierTransaction }))),
  },
  {
    code: 'ZW06',
    titleKey: 'titles.ZW06',
    permission: 'suppliers.manage',
    helpKey: 'transaction.ZW06',
    component: lazy(() => import('@/features/catalog/SupplierProductsTransaction').then((module) => ({ default: module.SupplierProductsTransaction }))),
  },
  {
    code: 'ZW61',
    titleKey: 'titles.ZW61',
    permission: 'suppliers.manage',
    helpKey: 'transaction.ZW61',
    component: lazy(() => import('@/features/catalog/SupplierProductActivityTransactions').then((module) => ({ default: module.CreateSupplierProductTransaction }))),
  },
  {
    code: 'ZW62',
    titleKey: 'titles.ZW62',
    permission: 'suppliers.manage',
    helpKey: 'transaction.ZW62',
    component: lazy(() => import('@/features/catalog/SupplierProductActivityTransactions').then((module) => ({ default: module.ChangeSupplierProductTransaction }))),
  },
  {
    code: 'ZW63',
    titleKey: 'titles.ZW63',
    permission: null,
    helpKey: 'transaction.ZW63',
    component: lazy(() => import('@/features/catalog/SupplierProductActivityTransactions').then((module) => ({ default: module.DisplaySupplierProductTransaction }))),
  },
  {
    code: 'SU01',
    titleKey: 'titles.SU01',
    permission: 'users.manage',
    helpKey: 'transaction.SU01',
    component: lazy(() => import('@/features/users/UserTransactions').then((module) => ({ default: module.ManageUsersTransaction }))),
  },
  {
    code: 'ZW91',
    titleKey: 'titles.ZW91',
    permission: 'users.manage',
    helpKey: 'transaction.ZW91',
    component: lazy(() => import('@/features/users/UserTransactions').then((module) => ({ default: module.CreateUserTransaction }))),
  },
  {
    code: 'ZW92',
    titleKey: 'titles.ZW92',
    permission: 'users.manage',
    helpKey: 'transaction.ZW92',
    component: lazy(() => import('@/features/users/UserTransactions').then((module) => ({ default: module.ChangeUserTransaction }))),
  },
  {
    code: 'ZW93',
    titleKey: 'titles.ZW93',
    permission: 'users.view',
    helpKey: 'transaction.ZW93',
    component: lazy(() => import('@/features/users/UserTransactions').then((module) => ({ default: module.DisplayUserTransaction }))),
  },
  {
    code: 'SUIM',
    titleKey: 'titles.SUIM',
    permission: 'users.view',
    helpKey: 'transaction.SUIM',
    component: lazy(() => import('@/features/users/UserListTransaction').then((module) => ({ default: module.UserListTransaction }))),
  },
  {
    code: 'PFCG',
    titleKey: 'titles.PFCG',
    permission: 'users.view',
    helpKey: 'transaction.PFCG',
    component: lazy(() => import('@/features/users/RoleTransaction').then((module) => ({ default: module.DisplayRoleTransaction }))),
  },
  {
    code: 'SU3',
    titleKey: 'titles.SU3',
    permission: null,
    helpKey: 'transaction.SU3',
    component: lazy(() =>
      import('@/features/userParameters/OwnDataTransaction').then((module) => ({ default: module.OwnDataTransaction })),
    ),
  },
];

// Carpetas del árbol por área funcional, como el menú de acceso de SAP.
export const menuTree: readonly MenuNode[] = [
  {
    kind: 'folder',
    id: 'logistics',
    labelKey: 'folders.logistics',
    children: [
      {
        kind: 'folder',
        id: 'warehouseStructure',
        labelKey: 'folders.warehouseStructure',
        children: [
          { kind: 'transaction', code: 'ZW01' },
          { kind: 'transaction', code: 'ZW11' },
          { kind: 'transaction', code: 'ZW12' },
          { kind: 'transaction', code: 'ZW13' },
          { kind: 'transaction', code: 'ZW02' },
          { kind: 'transaction', code: 'ZW21' },
          { kind: 'transaction', code: 'ZW22' },
          { kind: 'transaction', code: 'ZW23' },
          { kind: 'transaction', code: 'LS01N' },
          { kind: 'transaction', code: 'LS02N' },
          { kind: 'transaction', code: 'LS03N' },
          { kind: 'transaction', code: 'LS10' },
          { kind: 'transaction', code: 'LX03' },
          { kind: 'transaction', code: 'ZW05' },
        ],
      },
      {
        kind: 'folder',
        id: 'inventory',
        labelKey: 'folders.inventory',
        children: [
          { kind: 'transaction', code: 'ZW04' },
          { kind: 'transaction', code: 'ZW42' },
          { kind: 'transaction', code: 'ZW43' },
          { kind: 'transaction', code: 'ZW35' },
          { kind: 'transaction', code: 'ZW36' },
          { kind: 'transaction', code: 'ZW37' },
        ],
      },
      {
        kind: 'folder',
        id: 'catalog',
        labelKey: 'folders.catalog',
        children: [
          { kind: 'transaction', code: 'MM01' },
          { kind: 'transaction', code: 'MM02' },
          { kind: 'transaction', code: 'MM03' },
          { kind: 'transaction', code: 'MM60' },
          { kind: 'transaction', code: 'ZW07' },
          { kind: 'transaction', code: 'ZW71' },
          { kind: 'transaction', code: 'ZW72' },
          { kind: 'transaction', code: 'ZW73' },
          { kind: 'transaction', code: 'ZW08' },
          { kind: 'transaction', code: 'ZW81' },
          { kind: 'transaction', code: 'ZW82' },
          { kind: 'transaction', code: 'ZW83' },
        ],
      },
    ],
  },
  {
    kind: 'folder',
    id: 'purchasing',
    labelKey: 'folders.purchasing',
    children: [
      {
        kind: 'folder',
        id: 'suppliers',
        labelKey: 'folders.suppliers',
        children: [
          { kind: 'transaction', code: 'XK01' },
          { kind: 'transaction', code: 'XK02' },
          { kind: 'transaction', code: 'XK03' },
          { kind: 'transaction', code: 'ZW06' },
          { kind: 'transaction', code: 'ZW61' },
          { kind: 'transaction', code: 'ZW62' },
          { kind: 'transaction', code: 'ZW63' },
        ],
      },
    ],
  },
  {
    kind: 'folder',
    id: 'system',
    labelKey: 'folders.system',
    children: [
      {
        kind: 'folder',
        id: 'userData',
        labelKey: 'folders.userData',
        children: [{ kind: 'transaction', code: 'SU3' }],
      },
      {
        kind: 'folder',
        id: 'users',
        labelKey: 'folders.users',
        children: [
          { kind: 'transaction', code: 'SU01' },
          { kind: 'transaction', code: 'ZW91' },
          { kind: 'transaction', code: 'ZW92' },
          { kind: 'transaction', code: 'ZW93' },
          { kind: 'transaction', code: 'SUIM' },
        ],
      },
      {
        kind: 'folder',
        id: 'roles',
        labelKey: 'folders.roles',
        children: [{ kind: 'transaction', code: 'PFCG' }],
      },
    ],
  },
];

const byCode = new Map(transactions.map((transaction) => [transaction.code, transaction]));

export function findTransaction(code: string): TransactionDefinition | undefined {
  return byCode.get(code.toUpperCase());
}
