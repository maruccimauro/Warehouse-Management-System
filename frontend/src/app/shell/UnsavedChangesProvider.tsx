import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useBlocker } from 'react-router';
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog';
import { UnsavedChangesContext } from './unsavedChanges';

interface PendingExit {
  proceed: () => void;
  cancel: () => void;
}

export function UnsavedChangesProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation('shell');
  // El ref responde en el mismo instante en que se navega; el estado activa beforeunload.
  const dirtyRef = useRef(false);
  const [dirty, setDirtyState] = useState(false);
  const [pending, setPending] = useState<PendingExit | null>(null);

  const setDirty = useCallback((value: boolean) => {
    dirtyRef.current = value;
    setDirtyState(value);
  }, []);

  // Toda navegación del router que cambia de pantalla: árbol, campo de comando, F3, Atrás
  // del navegador. Los cambios de parámetros dentro de la misma pantalla no se bloquean.
  const blocker = useBlocker(
    ({ currentLocation, nextLocation }) => dirtyRef.current && currentLocation.pathname !== nextLocation.pathname,
  );

  // Cierre o recarga de la pestaña: el navegador muestra su propio aviso.
  useEffect(() => {
    if (!dirty) {
      return;
    }

    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
    };

    window.addEventListener('beforeunload', onBeforeUnload);

    return () => {
      window.removeEventListener('beforeunload', onBeforeUnload);
    };
  }, [dirty]);

  const confirmDiscard = useCallback((proceed: () => void) => {
    if (!dirtyRef.current) {
      proceed();

      return;
    }

    setPending({
      proceed: () => {
        setPending(null);
        proceed();
      },
      cancel: () => {
        setPending(null);
      },
    });
  }, []);

  const exit: PendingExit | null =
    blocker.state === 'blocked'
      ? {
          proceed: () => {
            blocker.proceed();
          },
          cancel: () => {
            blocker.reset();
          },
        }
      : pending;

  const value = useMemo(() => ({ dirty, setDirty, confirmDiscard }), [dirty, setDirty, confirmDiscard]);

  return (
    <UnsavedChangesContext value={value}>
      {children}
      <ConfirmDialog
        open={exit !== null}
        title={t('unsavedChanges.title')}
        message={t('unsavedChanges.message')}
        confirmLabel={t('unsavedChanges.discard')}
        cancelLabel={t('unsavedChanges.keepEditing')}
        onConfirm={() => exit?.proceed()}
        onCancel={() => exit?.cancel()}
      />
    </UnsavedChangesContext>
  );
}
