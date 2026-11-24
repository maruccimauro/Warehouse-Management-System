import { useEffect, useId, useLayoutEffect, useRef } from 'react';
import { Button } from './Button';

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  message: string;
  confirmLabel: string;
  cancelLabel: string;
  onConfirm: () => void;
  onCancel: () => void;
}

const confirmValue = 'confirm';

// Confirmación modal sobre <dialog> nativo con showModal(): el navegador aporta la página
// inerte, la contención del foco, Esc y la devolución del foco al cerrar. El foco inicial va
// a la acción segura (cancelar), como el botón por defecto de los popups de SAP.
export function ConfirmDialog({ open, title, message, confirmLabel, cancelLabel, onConfirm, onCancel }: ConfirmDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const cancelRef = useRef<HTMLButtonElement>(null);
  const titleId = useId();
  const messageId = useId();
  const latest = useRef({ onConfirm, onCancel });

  useLayoutEffect(() => {
    latest.current = { onConfirm, onCancel };
  });

  useEffect(() => {
    const dialog = dialogRef.current;

    if (dialog === null) {
      return;
    }

    if (open && !dialog.open) {
      dialog.returnValue = '';
      dialog.showModal();
      cancelRef.current?.focus();
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  return (
    <dialog
      ref={dialogRef}
      className="confirm-dialog"
      aria-labelledby={titleId}
      aria-describedby={messageId}
      onClose={(event) => {
        // Esc y el gesto de volver cierran sin valor: cuentan como cancelar.
        const confirmed = event.currentTarget.returnValue === confirmValue;
        event.currentTarget.returnValue = '';

        if (confirmed) {
          latest.current.onConfirm();
        } else {
          latest.current.onCancel();
        }
      }}
    >
      <h1 id={titleId} className="border-b border-border bg-surface-alt px-4 py-2 text-sm font-semibold">
        {title}
      </h1>
      <p id={messageId} className="px-4 py-3">
        {message}
      </p>
      <div className="flex flex-wrap justify-end gap-2 border-t border-border px-4 py-2">
        <Button
          onClick={() => {
            dialogRef.current?.close(confirmValue);
          }}
        >
          {confirmLabel}
        </Button>
        <Button
          ref={cancelRef}
          variant="primary"
          onClick={() => {
            dialogRef.current?.close();
          }}
        >
          {cancelLabel}
        </Button>
      </div>
    </dialog>
  );
}
