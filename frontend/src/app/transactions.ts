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
