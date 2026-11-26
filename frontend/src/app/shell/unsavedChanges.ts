import { createContext, use, useEffect } from 'react';

// Aviso de datos sin grabar, equivalente a POPUP_TO_CONFIRM_LOSS_OF_DATA de SAP. La
// transacción declara si tiene cambios; el armazón intercepta toda salida (F3, Mayús+F3,
// /n, árbol, botón Atrás del navegador, cierre de sesión, cierre o recarga de la pestaña).
export interface UnsavedChangesContextValue {
  // La transacción abierta tiene cambios sin grabar: lo muestra el indicador junto al título.
  dirty: boolean;
  setDirty: (dirty: boolean) => void;
  // Ejecuta proceed directamente sin cambios pendientes; con cambios, después de confirmar.
  confirmDiscard: (proceed: () => void) => void;
}

export const UnsavedChangesContext = createContext<UnsavedChangesContextValue | undefined>(undefined);

export function useUnsavedChangesContext(): UnsavedChangesContextValue {
  const value = use(UnsavedChangesContext);

  if (value === undefined) {
    throw new Error('useUnsavedChanges se usó fuera de UnsavedChangesProvider.');
  }

  return value;
}

// Lo llama la transacción con su estado de cambios; al desmontarse queda sin cambios.
export function useUnsavedChanges(dirty: boolean): void {
  const { setDirty } = useUnsavedChangesContext();

  useEffect(() => {
    setDirty(dirty);
  }, [dirty, setDirty]);

  useEffect(
    () => () => {
      setDirty(false);
    },
    [setDirty],
  );
}
