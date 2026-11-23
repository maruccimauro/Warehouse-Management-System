import type { ParseKeys } from 'i18next';
import type { ReactNode } from 'react';
import { OwnDataTransaction } from '@/features/userParameters/OwnDataTransaction';

// Registro de transacciones (ADR-016). Cada etapa agrega las suyas con el código de SAP
// cuando hay equivalente y el rango ZW para las propias. Desde E2 cada una declara el
// permiso que exige y el árbol y el campo de comando se filtran por los permisos del usuario.
export interface TransactionDefinition {
  code: string;
  titleKey: ParseKeys<'transactions'>;
  element: ReactNode;
}

export type MenuNode =
  | { kind: 'folder'; id: string; labelKey: ParseKeys<'transactions'>; children: readonly MenuNode[] }
  | { kind: 'transaction'; code: string };

export const transactions: readonly TransactionDefinition[] = [
  { code: 'SU3', titleKey: 'titles.SU3', element: <OwnDataTransaction /> },
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
