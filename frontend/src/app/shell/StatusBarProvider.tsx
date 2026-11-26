import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { StatusBarContext, type StatusMessage } from './statusMessages';

export function StatusBarProvider({ children }: { children: ReactNode }) {
  const [message, setMessage] = useState<StatusMessage>();
  const [sequence, setSequence] = useState(0);

  const show = useCallback((next: StatusMessage) => {
    setMessage(next);
    setSequence((current) => current + 1);
  }, []);

  const clear = useCallback(() => {
    setMessage(undefined);
  }, []);

  const value = useMemo(() => ({ message, sequence, show, clear }), [message, sequence, show, clear]);

  return <StatusBarContext value={value}>{children}</StatusBarContext>;
}
