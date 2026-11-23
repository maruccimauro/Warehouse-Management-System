import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react';
import { ShellActionsContext, type ShellAction, type TransactionActions } from './shellActions';

const none: Readonly<Record<ShellAction, boolean>> = { save: false, execute: false, back: false };

function availabilityOf(actions: TransactionActions): Record<ShellAction, boolean> {
  return { save: actions.save !== undefined, execute: actions.execute !== undefined, back: actions.back !== undefined };
}

export function ShellActionsProvider({ children }: { children: ReactNode }) {
  const actionsRef = useRef<TransactionActions>({});
  const [available, setAvailable] = useState(none);

  const register = useCallback((actions: TransactionActions) => {
    actionsRef.current = actions;
    setAvailable(availabilityOf(actions));

    return () => {
      // Solo limpia si nadie registró otras acciones después (cambio de transacción).
      if (actionsRef.current === actions) {
        actionsRef.current = {};
        setAvailable(none);
      }
    };
  }, []);

  const run = useCallback((action: ShellAction): boolean => {
    const handler = actionsRef.current[action];

    if (handler === undefined) {
      return false;
    }

    handler();

    return true;
  }, []);

  const value = useMemo(() => ({ available, register, run }), [available, register, run]);

  return <ShellActionsContext value={value}>{children}</ShellActionsContext>;
}
