import { useEffect, useRef } from 'react';
import type { ShellAction } from './shellActions';

interface ShellKeyboardOptions {
  inTransaction: boolean;
  back: () => void;
  exit: () => void;
  run: (action: ShellAction) => boolean;
}

// Teclas de función de SAP GUI (ADR-016): F3 atrás, Mayús+F3 finalizar, F8 ejecutar y
// Ctrl+S grabar. F3 y Ctrl+S se interceptan siempre para que el navegador no abra la
// búsqueda ni el guardado de página; F8 solo cuando la transacción ejecuta algo.
export function useShellKeyboard(options: ShellKeyboardOptions): void {
  const latest = useRef(options);

  useEffect(() => {
    latest.current = options;
  });

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.isComposing || event.defaultPrevented) {
        return;
      }

      const { inTransaction, back, exit, run } = latest.current;

      if (event.key === 'F3') {
        event.preventDefault();

        if (!inTransaction) {
          return;
        }

        if (event.shiftKey) {
          exit();
        } else {
          back();
        }

        return;
      }

      if (event.key === 'F8' && run('execute')) {
        event.preventDefault();

        return;
      }

      if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        run('save');
      }
    };

    window.addEventListener('keydown', onKeyDown);

    return () => {
      window.removeEventListener('keydown', onKeyDown);
    };
  }, []);
}
