import { useEffect, useId, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { PasswordChangeForm } from './PasswordChangeForm';

interface PasswordChangeDialogProps {
  open: boolean;
  onChanged: () => void;
  onClose: () => void;
}

// Cambio voluntario desde SU3, en un <dialog> modal nativo: Esc y Cancelar lo cierran y
// el navegador devuelve el foco al botón que lo abrió. El formulario se monta solo con el
// diálogo abierto, así cada apertura empieza con los campos vacíos.
export function PasswordChangeDialog({ open, onChanged, onClose }: PasswordChangeDialogProps) {
  const { t } = useTranslation('auth');
  const dialogRef = useRef<HTMLDialogElement>(null);
  const titleId = useId();

  useEffect(() => {
    const dialog = dialogRef.current;

    if (dialog === null) {
      return;
    }

    if (open && !dialog.open) {
      dialog.showModal();
      // El efecto del formulario corre antes que este: el foco inicial se fija después de
      // abrir el diálogo, en la contraseña actual.
      dialog.querySelector<HTMLElement>('input')?.focus();
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  return (
    <dialog ref={dialogRef} className="confirm-dialog w-[min(40rem,calc(100vw-2rem))]" aria-labelledby={titleId} onClose={onClose}>
      <h1 id={titleId} className="border-b border-border bg-surface-alt px-4 py-2 text-sm font-semibold">
        {t('passwordChange.title')}
      </h1>
      {open && (
        <div className="grid gap-3 p-4">
          <p className="text-ink-muted">{t('passwordChange.voluntaryDescription')}</p>
          <PasswordChangeForm
            onSuccess={onChanged}
            secondaryAction={{
              label: t('passwordChange.cancel'),
              onClick: () => dialogRef.current?.close(),
            }}
          />
        </div>
      )}
    </dialog>
  );
}
