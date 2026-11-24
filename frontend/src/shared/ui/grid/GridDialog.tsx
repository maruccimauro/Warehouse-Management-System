import { useEffect, useId, useLayoutEffect, useRef, type ReactNode, type SubmitEvent } from 'react';

interface GridDialogProps {
  open: boolean;
  title: string;
  onCancel: () => void;
  children: ReactNode;
}

// Ventana modal de la grilla (filtro y disposición), como los popups de la ALV. Mismo enfoque
// que ConfirmDialog: <dialog> nativo con showModal(), que aporta la página inerte, la contención
// del foco, Esc y la devolución del foco al control que la abrió.
export function GridDialog({ open, title, onCancel, children }: GridDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const latestCancel = useRef(onCancel);

  useLayoutEffect(() => {
    latestCancel.current = onCancel;
  });

  useEffect(() => {
    const dialog = dialogRef.current;

    if (dialog === null) {
      return;
    }

    if (open && !dialog.open) {
      dialog.showModal();
      dialog.querySelector<HTMLElement>('input, select, button')?.focus();
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  return (
    <dialog
      ref={dialogRef}
      className="confirm-dialog"
      aria-labelledby={titleId}
      onClose={() => {
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
        onSubmit();
      }}
    >
      <div className="max-h-[60dvh] overflow-auto px-4 py-3">{children}</div>
      <div className="flex flex-wrap justify-end gap-2 border-t border-border px-4 py-2">{footer}</div>
    </form>
  );
}
