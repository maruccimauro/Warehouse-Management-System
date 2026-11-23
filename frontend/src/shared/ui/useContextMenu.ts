import { useCallback, useState, type MouseEvent } from 'react';

export interface ContextMenuState<T> {
  // Sobre qué se abrió el menú. Los ítems se calculan en cada render a partir de esto, así
  // reflejan el estado actual (por ejemplo, una casilla recién marcada).
  target: T;
  x: number;
  y: number;
  // Elemento que recupera el foco al cerrar el menú.
  origin: HTMLElement | null;
}

export interface ContextMenuController<T> {
  state: ContextMenuState<T> | null;
  open: (event: MouseEvent<HTMLElement>, target: T, origin?: HTMLElement) => void;
  close: () => void;
}

// Mayús+F10 y la tecla de menú también disparan contextmenu, sin coordenadas de puntero:
// Chromium lo marca con pointerType vacío y otros navegadores mandan (0, 0).
function isKeyboardInvoked(event: MouseEvent<HTMLElement>): boolean {
  const native = event.nativeEvent as Partial<PointerEvent>;

  return native.pointerType === '' || (event.clientX === 0 && event.clientY === 0);
}

export function useContextMenu<T>(): ContextMenuController<T> {
  const [state, setState] = useState<ContextMenuState<T> | null>(null);

  const open = useCallback((event: MouseEvent<HTMLElement>, target: T, origin?: HTMLElement) => {
    event.preventDefault();
    event.stopPropagation();

    const focused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const reference = origin ?? (event.target instanceof Element ? event.target : event.currentTarget);
    let x = event.clientX;
    let y = event.clientY;

    // Abierto con el teclado: el menú aparece debajo del elemento, no en la esquina.
    if (isKeyboardInvoked(event)) {
      const rect = reference.getBoundingClientRect();

      x = rect.left;
      y = rect.bottom;
    }

    setState({ target, x, y, origin: origin ?? focused });
  }, []);

  const close = useCallback(() => {
    setState(null);
  }, []);

  return { state, open, close };
}
