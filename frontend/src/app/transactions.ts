import type { ParseKeys } from 'i18next';
import { lazy, type ComponentType, type LazyExoticComponent } from 'react';
import type { PermissionName } from '@/features/auth/permissions';
import type { HelpKey } from '@/shared/help/helpLinks';

// Registro de transacciones (ADR-016). Cada etapa agrega las suyas con el código de SAP
// cuando hay equivalente y el rango ZW para las propias.
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
    code: 'ZW02',
    titleKey: 'titles.ZW02',
    permission: 'locations.manage',
    helpKey: 'transaction.ZW02',
    component: lazy(() => import('@/features/warehouses/ZonesTransaction').then((module) => ({ default: module.ZonesTransaction }))),
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
          { kind: 'transaction', code: 'ZW02' },
          { kind: 'transaction', code: 'LS01N' },
          { kind: 'transaction', code: 'LS02N' },
          { kind: 'transaction', code: 'LS03N' },
          { kind: 'transaction', code: 'LS10' },
          { kind: 'transaction', code: 'LX03' },
          { kind: 'transaction', code: 'ZW05' },
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
    ],
  },
];

const byCode = new Map(transactions.map((transaction) => [transaction.code, transaction]));

export function findTransaction(code: string): TransactionDefinition | undefined {
  return byCode.get(code.toUpperCase());
}
