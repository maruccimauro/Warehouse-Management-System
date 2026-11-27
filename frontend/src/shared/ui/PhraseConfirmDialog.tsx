import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { Button } from './Button';

export type PhraseConfirmTone = 'danger' | 'warning';

interface PhraseConfirmDialogProps {
  open: boolean;
  tone: PhraseConfirmTone;
  title: string;
  message: string;
  // Frase exacta que hay que escribir para habilitar la confirmación.
  phrase: string;
  inputLabel: string;
  confirmLabel: string;
  cancelLabel: string;
  onConfirm: () => void;
  onCancel: () => void;
}

const toneClasses: Record<PhraseConfirmTone, { bar: string; title: string }> = {
  danger: { bar: 'border-t-4 border-t-error', title: 'bg-error-soft text-error' },
  warning: { bar: 'border-t-4 border-t-warning', title: 'bg-warning-soft text-warning' },
};

const confirmValue = 'confirm';

// Confirmación de una acción de alto impacto (asignar un rol crítico, D16.4 de E3): como
// ConfirmDialog, sobre <dialog> nativo, pero el botón de confirmar se habilita solo cuando se
// escribe la frase exacta. Rojo para lo más grave y ámbar para lo intermedio. El foco inicial
// va al campo de la frase; Esc y Cancelar cierran sin confirmar.
export function PhraseConfirmDialog({
  open,
  tone,
  title,
  message,
  phrase,
  inputLabel,
  confirmLabel,
  cancelLabel,
  onConfirm,
  onCancel,
}: PhraseConfirmDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const titleId = useId();
  const messageId = useId();
  const inputId = useId();
  const [typed, setTyped] = useState('');
  const latest = useRef({ onConfirm, onCancel });
  const matches = typed.trim() === phrase;

  useLayoutEffect(() => {
    latest.current = { onConfirm, onCancel };
  });

  useEffect(() => {
    const dialog = dialogRef.current;

    if (dialog === null) {
      return;
    }

    if (open && !dialog.open) {
      setTyped('');
      dialog.returnValue = '';
      dialog.showModal();
      inputRef.current?.focus();
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open, phrase]);

  return (
    <dialog
      ref={dialogRef}
      className={`confirm-dialog ${toneClasses[tone].bar}`}
      aria-labelledby={titleId}
      aria-describedby={messageId}
      onClose={(event) => {
        const confirmed = event.currentTarget.returnValue === confirmValue;
        event.currentTarget.returnValue = '';

        if (confirmed) {
          latest.current.onConfirm();
        } else {
          latest.current.onCancel();
        }
      }}
    >
      <h1 id={titleId} className={`border-b border-border px-4 py-2 text-sm font-semibold ${toneClasses[tone].title}`}>
        {title}
      </h1>
      <form
        method="dialog"
        className="grid gap-3 px-4 py-3"
        onSubmit={(event) => {
          // Intro en el campo confirma solo con la frase completa.
          event.preventDefault();

          if (matches) {
            dialogRef.current?.close(confirmValue);
          }
        }}
      >
        <p id={messageId}>{message}</p>
        <div className="grid gap-1">
          <label htmlFor={inputId}>{inputLabel}</label>
          <code className="font-semibold">{phrase}</code>
          <input
            ref={inputRef}
            id={inputId}
            type="text"
            autoComplete="off"
            spellCheck={false}
            className="min-h-7 rounded-sm border border-border-strong bg-surface px-2"
            value={typed}
            onChange={(event) => {
              setTyped(event.target.value);
            }}
          />
        </div>
        <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-2">
          <Button type="submit" disabled={!matches}>
            {confirmLabel}
          </Button>
          <Button
            variant="primary"
            onClick={() => {
              dialogRef.current?.close();
            }}
          >
            {cancelLabel}
          </Button>
        </div>
      </form>
    </dialog>
  );
}
