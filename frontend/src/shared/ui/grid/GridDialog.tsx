import { useEffect, useId, useLayoutEffect, useRef, type ReactNode, type SubmitEvent } from 'react';

interface GridDialogProps {
  open: boolean;
  title: string;
  onCancel: () => void;
  // wide: para diálogos con una lista (la ayuda de búsqueda F4).
  size?: 'default' | 'wide';
  // Elemento que recibe el foco al abrir; sin él, el primer campo o botón del diálogo.
  initialFocus?: (dialog: HTMLDialogElement) => HTMLElement | null | undefined;
  children: ReactNode;
}

// Ventana modal de la grilla (filtro y disposición), como los popups de la ALV. Mismo enfoque
// que ConfirmDialog: <dialog> nativo con showModal(), que aporta la página inerte, la contención
// del foco, Esc y la devolución del foco al control que la abrió.
export function GridDialog({ open, title, onCancel, size = 'default', initialFocus, children }: GridDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const latestCancel = useRef(onCancel);
  const latestInitialFocus = useRef(initialFocus);

  useLayoutEffect(() => {
    latestCancel.current = onCancel;
    latestInitialFocus.current = initialFocus;
  });

  useEffect(() => {
    const dialog = dialogRef.current;

    if (dialog === null) {
      return;
    }

    if (open && !dialog.open) {
      dialog.showModal();
      (latestInitialFocus.current?.(dialog) ?? dialog.querySelector<HTMLElement>('input, select, button'))?.focus();
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  return (
    <dialog
      ref={dialogRef}
      className={size === 'wide' ? 'confirm-dialog confirm-dialog-wide' : 'confirm-dialog'}
      aria-labelledby={titleId}
      onClose={(event) => {
        // React propaga el close de un diálogo anidado (la disposición abierta desde la grilla
        // de un F4) a los diálogos que lo contienen: solo cuenta el cierre de este.
        if (event.target !== event.currentTarget) {
          return;
        }

        // Esc o el gesto de volver cuentan como cancelar. Cerrar desde el padre también pasa
        // por acá y no cambia nada: el padre ya lo dio por cerrado.
        latestCancel.current();
      }}
    >
      {open && (
        <>
          <h1 id={titleId} className="border-b border-border bg-surface-alt px-4 py-2 text-sm font-semibold">
            {title}
          </h1>
          {children}
        </>
      )}
    </dialog>
  );
}

interface GridDialogFormProps {
  onSubmit: () => void;
  footer: ReactNode;
  children: ReactNode;
}

// Cuerpo con formulario: Enter en un campo aplica, como el botón por defecto del popup.
export function GridDialogForm({ onSubmit, footer, children }: GridDialogFormProps) {
  return (
    <form
      onSubmit={(event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        // El submit no sigue a los formularios que contienen el diálogo en el árbol de React
        // (el del F4 o el de la transacción).
        event.stopPropagation();
        onSubmit();
      }}
    >
      <div className="max-h-[60dvh] overflow-auto px-4 py-3">{children}</div>
      <div className="flex flex-wrap justify-end gap-2 border-t border-border px-4 py-2">{footer}</div>
    </form>
  );
}
