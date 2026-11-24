import { useMemo } from 'react';
import { findTransaction, transactions, type TransactionDefinition } from '@/app/transactions';
import { permissionSet } from '@/features/auth/permissions';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';

// Resultado de buscar un código: como SAP, una transacción inexistente y una sin
// autorización dan mensajes distintos. Los códigos están en el bundle, así que distinguirlas
// no revela nada.
export type TransactionAccess =
  | { status: 'allowed'; transaction: TransactionDefinition }
  | { status: 'forbidden'; transaction: TransactionDefinition }
  | { status: 'unknown' };

export interface TransactionAccessValue {
  resolve: (code: string) => TransactionAccess;
  // La transacción, solo si existe y el usuario puede abrirla.
  allowed: (code: string) => TransactionDefinition | undefined;
  allowedTransactions: readonly TransactionDefinition[];
}

export function resolveTransaction(code: string, permissions: ReadonlySet<string>): TransactionAccess {
  const transaction = findTransaction(code);

  if (transaction === undefined) {
    return { status: 'unknown' };
  }

  const permitted = transaction.permission === null || permissions.has(transaction.permission);

  return { status: permitted ? 'allowed' : 'forbidden', transaction };
}

// Filtro del árbol, del campo de comando y de las rutas por me.permissions (ADR-016). Es
// usabilidad: el backend vuelve a autorizar cada operación.
export function useTransactionAccess(): TransactionAccessValue {
  const { permissions } = useAuthenticatedUser();

  return useMemo(() => {
    const granted = permissionSet(permissions);
    const resolve = (code: string) => resolveTransaction(code, granted);
    const allowed = (code: string) => {
      const access = resolve(code);

      return access.status === 'allowed' ? access.transaction : undefined;
    };

    return {
      resolve,
      allowed,
      allowedTransactions: transactions.filter((transaction) => allowed(transaction.code) !== undefined),
    };
  }, [permissions]);
}
