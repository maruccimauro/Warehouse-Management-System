import { useEffect, useId, useLayoutEffect, useRef, type KeyboardEvent } from 'react';
import type { ContextMenuItem } from './contextMenuItems';
import { Icon } from './icons';
import type { ContextMenuState } from './useContextMenu';

// Detección de capacidades una sola vez. Popover (Baseline 2025) lleva el menú a la capa
// superior con cierre ligero; sin soporte se muestra con hidden y el cierre lo hace el script.
// El anclaje por CSS necesita position-area y el source de showPopover.
//
// No se usa focusgroup: en Chrome 157 el nativo deja de avanzar al llegar a un separador y
// no maneja Inicio ni Fin. Las flechas las maneja siempre el script y los roles son explícitos,
// así el comportamiento es el mismo en todos los navegadores y en las pruebas.
const supportsPopover = Object.hasOwn(HTMLElement.prototype, 'popover');
const supportsAnchoring =
  supportsPopover &&
  typeof CSS !== 'undefined' &&
  typeof CSS.supports === 'function' &&
  CSS.supports('position-area: block-end') &&
  typeof ToggleEvent !== 'undefined' &&
  'source' in ToggleEvent.prototype;

const viewportMargin = 4;
// Las casillas esperan un instante antes de cerrar para que se vea la marca nueva.
const checkboxCloseDelay = 300;

const itemClass =
  'flex min-h-7 w-full cursor-default items-center gap-2 rounded-sm px-2 text-start text-ink outline-none focus:bg-selected focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus aria-disabled:cursor-not-allowed aria-disabled:opacity-40 pointer-coarse:min-h-11';

function enabledItems(menu: HTMLElement): HTMLElement[] {
  return Array.from(menu.querySelectorAll<HTMLElement>('button')).filter(
    (item) => item.getAttribute('aria-disabled') !== 'true',
  );
}

// Sin anclaje por CSS: el menú se ubica en el punto y se corre hacia adentro de la ventana
// cuando no entra, como hace el menú del sistema operativo.
function placeInViewport(menu: HTMLElement, x: number, y: number): void {
  const { width, height } = menu.getBoundingClientRect();
  const left = x + width > window.innerWidth - viewportMargin ? Math.max(viewportMargin, x - width) : x;
  const top = y + height > window.innerHeight - viewportMargin ? Math.max(viewportMargin, y - height) : y;

  menu.style.left = `${left}px`;
  menu.style.top = `${top}px`;
}

interface ContextMenuProps<T> {
  label: string;
  state: ContextMenuState<T> | null;
  items: readonly ContextMenuItem[];
  onClose: () => void;
}

// Menú contextual (patrón Menu de WAI-ARIA y guía de menús emergentes): se abre con clic
// secundario, Mayús+F10 o la tecla de menú; flechas, Inicio y Fin recorren los ítems; Escape
// y Tab cierran y devuelven el foco al elemento de origen; un clic afuera cierra.
export function ContextMenu<T>({ label, state, items, onClose }: ContextMenuProps<T>) {
  const id = useId();
  const anchorRef = useRef<HTMLSpanElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const openRef = useRef(false);
  const closeTimer = useRef<number | undefined>(undefined);
  const latestClose = useRef(onClose);
  const isOpen = state !== null;

  useLayoutEffect(() => {
    latestClose.current = onClose;
  });

  const restoreAndClose = () => {
    window.clearTimeout(closeTimer.current);

    if (state?.origin?.isConnected === true) {
      state.origin.focus({ preventScroll: true });
    }

    latestClose.current();
  };

  // Abre, ubica y enfoca el primer ítem habilitado en cada apertura.
  useLayoutEffect(() => {
    const menu = menuRef.current;
    openRef.current = state !== null;

    if (menu === null) {
      return;
    }

    if (state === null) {
      window.clearTimeout(closeTimer.current);

      if (supportsPopover && menu.matches(':popover-open')) {
        menu.hidePopover();
      }

      return;
    }

    if (supportsPopover && !menu.matches(':popover-open')) {
      menu.showPopover(anchorRef.current === null ? undefined : { source: anchorRef.current });
    }

    if (!supportsAnchoring) {
      placeInViewport(menu, state.x, state.y);
    }

    enabledItems(menu)[0]?.focus({ preventScroll: true });
  }, [state]);

  // Cierre ligero: clic o foco fuera del menú, ventana que pierde el foco o cambia de tamaño.
  // Con Popover el navegador también cierra con Escape y clic afuera; toggle sincroniza el estado.
  useEffect(() => {
    const menu = menuRef.current;

    if (!isOpen || menu === null) {
      return undefined;
    }

    const closeIfOutside = (event: Event) => {
      if (event.target instanceof Node && !menu.contains(event.target)) {
        latestClose.current();
      }
    };
    const close = () => {
      latestClose.current();
    };
    const onToggle = (event: Event) => {
      if ((event as ToggleEvent).newState === 'closed' && openRef.current) {
        latestClose.current();
      }
    };

    document.addEventListener('pointerdown', closeIfOutside, true);
    document.addEventListener('focusin', closeIfOutside);
    window.addEventListener('blur', close);
    window.addEventListener('resize', close);
    menu.addEventListener('toggle', onToggle);

    return () => {
      document.removeEventListener('pointerdown', closeIfOutside, true);
      document.removeEventListener('focusin', closeIfOutside);
      window.removeEventListener('blur', close);
      window.removeEventListener('resize', close);
      menu.removeEventListener('toggle', onToggle);
    };
  }, [isOpen]);

  useEffect(
    () => () => {
      window.clearTimeout(closeTimer.current);
    },
    [],
  );

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape' || event.key === 'Tab') {
      event.preventDefault();
      event.stopPropagation();
      restoreAndClose();

      return;
    }

    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      return;
    }

    const current = enabledItems(event.currentTarget);
    const index = current.findIndex((item) => item === document.activeElement);
    const last = current.length - 1;
    let next = 0;

    if (event.key === 'End') {
      next = last;
    } else if (event.key === 'ArrowDown') {
      next = index === -1 || index === last ? 0 : index + 1;
    } else if (event.key === 'ArrowUp') {
      next = index <= 0 ? last : index - 1;
    }

    event.preventDefault();
    current[next]?.focus({ preventScroll: true });
  };

  const renderItem = (item: ContextMenuItem) => {
    if (item.kind === 'separator') {
      return <hr key={item.id} className="my-1 border-border" />;
    }

    const focusOnHover = (target: HTMLElement) => {
      if (document.activeElement !== target && target.getAttribute('aria-disabled') !== 'true') {
        target.focus({ preventScroll: true });
      }
    };

    if (item.kind === 'checkbox') {
      const toggle = () => {
        item.onChange(!item.checked);
      };

      return (
        <button
          key={item.id}
          type="button"
          role="menuitemcheckbox"
          aria-checked={item.checked}
          tabIndex={-1}
          className={itemClass}
          onPointerMove={(event) => {
            focusOnHover(event.currentTarget);
          }}
          onKeyDown={(event) => {
            // Espacio alterna y deja el menú abierto; Enter alterna y cierra (APG).
            if (event.key === ' ') {
              event.preventDefault();
              toggle();
            }
          }}
          onClick={() => {
            toggle();
            window.clearTimeout(closeTimer.current);
            closeTimer.current = window.setTimeout(restoreAndClose, checkboxCloseDelay);
          }}
        >
          <span className="flex size-4 shrink-0 items-center justify-center text-accent">
            {item.checked && <Icon name="check" />}
          </span>
          <span>{item.label}</span>
        </button>
      );
    }

    return (
      <button
        key={item.id}
        type="button"
        role="menuitem"
        tabIndex={-1}
        className={itemClass}
        aria-disabled={item.disabled === true ? true : undefined}
        aria-keyshortcuts={item.shortcut?.aria}
        onPointerMove={(event) => {
          focusOnHover(event.currentTarget);
        }}
        onClick={() => {
          if (item.disabled === true) {
            return;
          }

          restoreAndClose();
          item.onSelect();
        }}
      >
        <span className="flex size-4 shrink-0 items-center justify-center text-ink-muted">
          {item.icon !== undefined && <Icon name={item.icon} />}
        </span>
        <span>{item.label}</span>
        {item.shortcut !== undefined && (
          <span aria-hidden="true" className="ms-auto ps-6 text-xs text-ink-muted">
            {item.shortcut.display}
          </span>
        )}
      </button>
    );
  };

  return (
    <>
      <span
        ref={anchorRef}
        aria-hidden="true"
        className="context-menu-anchor"
        style={state === null ? undefined : { left: `${state.x}px`, top: `${state.y}px` }}
      />
      <div
        ref={menuRef}
        id={id}
        popover={supportsPopover ? 'auto' : undefined}
        hidden={!supportsPopover && !isOpen}
        data-anchored={supportsAnchoring ? '' : undefined}
        className="context-menu"
      >
        <div
          role="menu"
          aria-label={label}
          className="flex flex-col"
          onKeyDown={onKeyDown}
        >
          {isOpen && items.map(renderItem)}
        </div>
      </div>
    </>
  );
}
