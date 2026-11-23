import { createContext, use, useEffect, useLayoutEffect, useRef } from 'react';

// Acciones de la barra de herramientas estándar que cada transacción puede habilitar
// (ADR-016): Grabar (Ctrl+S), Ejecutar (F8) y Atrás (F3). Sin back propio, Atrás vuelve al menú.
export interface TransactionActions {
  save?: (() => void) | undefined;
  execute?: (() => void) | undefined;
  back?: (() => void) | undefined;
}

export type ShellAction = keyof TransactionActions;

export interface ShellActionsContextValue {
  available: Readonly<Record<ShellAction, boolean>>;
  register: (actions: TransactionActions) => () => void;
  run: (action: ShellAction) => boolean;
}

export const ShellActionsContext = createContext<ShellActionsContextValue | undefined>(undefined);

export function useShellActions(): ShellActionsContextValue {
  const value = use(ShellActionsContext);

  if (value === undefined) {
    throw new Error('useShellActions se usó fuera de ShellActionsProvider.');
  }

  return value;
}

// Registra las acciones de la transacción mientras está montada. Las funciones pueden
// cambiar en cada render: se lee siempre la última, y solo se vuelve a registrar cuando
// cambia qué acciones existen.
export function useTransactionActions(actions: TransactionActions): void {
  const { register } = useShellActions();
  const latest = useRef(actions);

  useLayoutEffect(() => {
    latest.current = actions;
  });

  const hasSave = actions.save !== undefined;
  const hasExecute = actions.execute !== undefined;
  const hasBack = actions.back !== undefined;

  useEffect(
    () =>
      register({
        save: hasSave ? () => latest.current.save?.() : undefined,
        execute: hasExecute ? () => latest.current.execute?.() : undefined,
        back: hasBack ? () => latest.current.back?.() : undefined,
      }),
    [register, hasSave, hasExecute, hasBack],
  );
}
