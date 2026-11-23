import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { StatusBarContext, type StatusMessage } from './statusMessages';

export function StatusBarProvider({ children }: { children: ReactNode }) {
  const [message, setMessage] = useState<StatusMessage>();

  const show = useCallback((next: StatusMessage) => {
    setMessage(next);
  }, []);

  const clear = useCallback(() => {
    setMessage(undefined);
  }, []);

  const value = useMemo(() => ({ message, show, clear }), [message, show, clear]);

  return <StatusBarContext value={value}>{children}</StatusBarContext>;
}
