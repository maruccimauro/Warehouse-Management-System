import type { ReactNode } from 'react';

// Botones de un formulario con envío propio (login, cambio de contraseña), alineados con la
// columna de los controles de FormFieldLayout.
export function FormActions({ children }: { children: ReactNode }) {
  return (
    <div className="grid sm:grid-cols-[minmax(8rem,12rem)_1fr] sm:gap-x-4">
      <div className="flex flex-wrap gap-2 sm:col-start-2">{children}</div>
    </div>
  );
}
