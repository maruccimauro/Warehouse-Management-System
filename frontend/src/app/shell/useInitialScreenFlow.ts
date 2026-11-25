import { useState } from 'react';

// Actividad de una transacción de datos maestros con trío (crear, modificar, visualizar),
// como los códigos 01, 02 y 03 de SAP.
export type MaintenanceActivity = 'create' | 'change' | 'display';

export interface InitialScreenFlow<TTarget, TEntry> {
  // Registro elegido en la pantalla inicial; undefined mientras se muestra la pantalla inicial.
  target: TTarget | undefined;
  // Lo último ingresado: F3 desde la ficha vuelve a la pantalla inicial con esos valores.
  last: TEntry | undefined;
  // F8 en la pantalla inicial: pasa a la ficha y recuerda lo ingresado.
  open: (target: TTarget) => void;
  // Grabar o F3 en la ficha: vuelve a la pantalla inicial.
  close: () => void;
}

// Flujo pantalla inicial y ficha de los tríos (LS0xN, MM0x, XK02 y XK03). Solo el estado de
// navegación: cada transacción conserva su lectura, sus validaciones y su ficha.
export function useInitialScreenFlow<TTarget, TEntry>(entryOf: (target: TTarget) => TEntry): InitialScreenFlow<TTarget, TEntry> {
  const [target, setTarget] = useState<TTarget>();
  const [last, setLast] = useState<TEntry>();

  return {
    target,
    last,
    open: (next) => {
      setLast(entryOf(next));
      setTarget(next);
    },
    close: () => {
      setTarget(undefined);
    },
  };
}
