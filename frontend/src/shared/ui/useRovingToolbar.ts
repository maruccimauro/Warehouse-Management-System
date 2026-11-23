import { useEffect, type RefObject } from 'react';

// Respaldo de focusgroup="toolbar" para navegadores sin soporte nativo: una sola parada de
// tabulación, flechas izquierda y derecha con vuelta al principio, Inicio y Fin. Con soporte
// nativo no hace nada, para no duplicar el comportamiento del navegador.
export function useRovingToolbar(ref: RefObject<HTMLElement | null>): void {
  useEffect(() => {
    const bar = ref.current;

    if (bar === null || 'focusGroup' in HTMLElement.prototype) {
      return undefined;
    }

    const items = () => Array.from(bar.querySelectorAll<HTMLElement>('button'));

    items().forEach((item, index) => {
      item.tabIndex = index === 0 ? 0 : -1;
    });

    const onFocusIn = (event: FocusEvent) => {
      const current = items();

      if (event.target instanceof HTMLElement && current.includes(event.target)) {
        current.forEach((item) => {
          item.tabIndex = item === event.target ? 0 : -1;
        });
      }
    };

    const onKeyDown = (event: KeyboardEvent) => {
      const current = items();
      const index = current.findIndex((item) => item === document.activeElement);

      if (index === -1 || current.length === 0) {
        return;
      }

      const rtl = getComputedStyle(bar).direction === 'rtl';
      const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
      const backward = rtl ? 'ArrowRight' : 'ArrowLeft';
      let next: number | undefined;

      if (event.key === forward) {
        next = (index + 1) % current.length;
      } else if (event.key === backward) {
        next = (index - 1 + current.length) % current.length;
      } else if (event.key === 'Home') {
        next = 0;
      } else if (event.key === 'End') {
        next = current.length - 1;
      }

      if (next !== undefined) {
        event.preventDefault();
        current[next]?.focus();
      }
    };

    bar.addEventListener('focusin', onFocusIn);
    bar.addEventListener('keydown', onKeyDown);

    return () => {
      bar.removeEventListener('focusin', onFocusIn);
      bar.removeEventListener('keydown', onKeyDown);
    };
  }, [ref]);
}
