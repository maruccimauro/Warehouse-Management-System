import { useEffect, useRef } from 'react';
import { useStatusBar } from './statusMessages';

// La transacción informa en qué pantalla está (pantalla inicial, ficha, selección, lista).
// Al pasar a otra, la barra de estado borra el mensaje de la pantalla anterior (D16.2 de E3).
// El montaje no cuenta: abrir la transacción ya es un cambio de contexto del armazón.
export function useScreenContext(screen: string): void {
  const { contextChanged } = useStatusBar();
  const previous = useRef(screen);

  useEffect(() => {
    if (previous.current === screen) {
      return;
    }

    previous.current = screen;
    contextChanged();
  }, [screen, contextChanged]);
}
