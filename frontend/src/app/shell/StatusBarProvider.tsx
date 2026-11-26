import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { StatusBarContext, type StatusMessage } from './statusMessages';

// Eventos que inician una acción del usuario. Se escuchan en captura, antes que el manejador
// de la pantalla, para saber si el mensaje vigente lo mostró la acción en curso.
const actionEvents = ['keydown', 'pointerdown', 'click'] as const;

// El mensaje queda en la barra hasta que lo reemplaza otro o cambia el contexto, nunca por
// tiempo (D16.2 de E3, ADR-016). Un cambio de contexto que llega con su propio mensaje (grabar
// y volver a la pantalla inicial, una transacción rechazada que vuelve al menú) lo conserva.
export function StatusBarProvider({ children }: { children: ReactNode }) {
  const [message, setMessage] = useState<StatusMessage>();
  const [sequence, setSequence] = useState(0);
  // Número del último mensaje mostrado y el que había al empezar la última acción del usuario.
  const shownRef = useRef(0);
  const actionStartRef = useRef(0);

  useEffect(() => {
    const onAction = () => {
      actionStartRef.current = shownRef.current;
    };

    for (const type of actionEvents) {
      document.addEventListener(type, onAction, true);
    }

    return () => {
      for (const type of actionEvents) {
        document.removeEventListener(type, onAction, true);
      }
    };
  }, []);

  const show = useCallback((next: StatusMessage) => {
    shownRef.current += 1;
    setMessage(next);
    setSequence((current) => current + 1);
  }, []);

  const clear = useCallback(() => {
    setMessage(undefined);
  }, []);

  const contextChanged = useCallback(() => {
    if (shownRef.current === actionStartRef.current) {
      setMessage(undefined);
    }
  }, []);

  const value = useMemo(() => ({ message, sequence, show, clear, contextChanged }), [message, sequence, show, clear, contextChanged]);

  return <StatusBarContext value={value}>{children}</StatusBarContext>;
}
