import type { ReactNode } from 'react';
import { FieldErrorIndicator } from './FieldErrorIndicator';

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
  // Nombre del campo para la ayuda F1 (data-help-field, E2.16).
  helpField?: string;
  children: ReactNode;
}

// Renglón de un campo de formulario (E2.22, D4): etiqueta a la izquierda y control a la
// derecha, como en SAP GUI; en pantallas angostas la etiqueta pasa arriba. Entre la etiqueta y
// el control queda el espacio fijo del ícono de error (FieldErrorIndicator), así un error no
// corre los campos; debajo del control va la ayuda persistente. Lo usan los bloques de
// transacción y los formularios de autenticación, que no son transacciones.
export function FormFieldLayout({
  htmlFor,
  label,
  required = false,
  description,
  descriptionId,
  error,
  errorId,
  helpField,
  children,
}: FormFieldLayoutProps) {
  return (
    <div
      data-help-field={helpField}
      className="field-row grid gap-1 sm:grid-cols-[minmax(8rem,12rem)_1fr] sm:items-start sm:gap-x-3"
    >
      <div className="flex items-start justify-between gap-1">
        <label htmlFor={htmlFor} className="text-ink-muted sm:pt-1">
          {label}
          {required && (
            <span aria-hidden="true" className="ms-0.5 text-error">
              *
            </span>
          )}
        </label>
        <FieldErrorIndicator id={errorId ?? `${htmlFor}-error`} error={error} />
      </div>
      <div className="grid min-w-0 gap-1">
        {children}
        {description !== undefined && (
          <p id={descriptionId} className="text-xs text-ink-muted">
            {description}
          </p>
        )}
      </div>
    </div>
  );
}
