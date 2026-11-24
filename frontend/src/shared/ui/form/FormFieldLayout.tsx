import type { ReactNode } from 'react';

interface FormFieldLayoutProps {
  // id del control, para el <label>.
  htmlFor: string;
  label: ReactNode;
  required?: boolean;
  // Texto de ayuda persistente y error, cada uno con su id para aria-describedby del control.
  description?: ReactNode;
  descriptionId?: string;
  error?: string | undefined;
  errorId?: string;
  children: ReactNode;
}

// Renglón de un campo de formulario (E2.22, D4): etiqueta a la izquierda y control a la
// derecha, como en SAP GUI; en pantallas angostas la etiqueta pasa arriba. Debajo del control
// van la ayuda y el error (D3). Lo usan los bloques de transacción y los formularios de
// autenticación, que no son transacciones.
export function FormFieldLayout({
  htmlFor,
  label,
  required = false,
  description,
  descriptionId,
  error,
  errorId,
  children,
}: FormFieldLayoutProps) {
  return (
    <div className="grid gap-1 sm:grid-cols-[minmax(8rem,12rem)_1fr] sm:items-start sm:gap-x-4 sm:[&>label]:pt-1">
      <label htmlFor={htmlFor} className="text-ink-muted">
        {label}
        {required && (
          <span aria-hidden="true" className="ms-0.5 text-error">
            *
          </span>
        )}
      </label>
      <div className="grid min-w-0 gap-1">
        {children}
        {description !== undefined && (
          <p id={descriptionId} className="text-xs text-ink-muted">
            {description}
          </p>
        )}
        {error !== undefined && (
          <p id={errorId} className="text-xs text-error">
            {error}
          </p>
        )}
      </div>
    </div>
  );
}
