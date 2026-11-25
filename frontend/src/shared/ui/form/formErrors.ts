import type { AnyFormApi } from '@tanstack/react-form';

// Errores de campo de TanStack Form (E2.22). Un esquema Zod (Standard Schema) deja issues con
// message; los errores del servidor y de la tabla de verificación se cargan como texto en
// errorMap.onServer.

export interface FieldErrorState {
  errors: readonly unknown[];
  isBlurred: boolean;
  errorMap: Readonly<Partial<Record<string, unknown>>>;
}

function messageOf(error: unknown): string | undefined {
  if (typeof error === 'string') {
    return error === '' ? undefined : error;
  }

  if (typeof error === 'object' && error !== null && 'message' in error && typeof error.message === 'string') {
    return error.message;
  }

  return undefined;
}

export function firstErrorMessage(errors: readonly unknown[]): string | undefined {
  for (const error of errors) {
    const message = messageOf(error);

    if (message !== undefined) {
      return message;
    }
  }

  return undefined;
}

// Error visible del campo (D2 de E2.22): después de salir del campo editado y, desde ahí, en
// cada cambio, como el modo onTouched; al grabar, en todos. Un campo vacío no se marca al salir
// de él, solo al grabar o ejecutar: recorrer la pantalla con Tab no llena de avisos los
// obligatorios que todavía no se completaron. Los del servidor y la tabla de verificación se
// ven siempre: llegan como respuesta a una acción del usuario.
export function visibleFieldError(meta: FieldErrorState | undefined, submitted: boolean, empty = false): string | undefined {
  if (meta === undefined) {
    return undefined;
  }

  const server = messageOf(meta.errorMap['onServer']);

  if (server !== undefined) {
    return server;
  }

  return submitted || (meta.isBlurred && !empty) ? firstErrorMessage(meta.errors) : undefined;
}

// Mínimo de la API del formulario que usan estas funciones: sirven para cualquier instancia
// de TanStack Form sin arrastrar sus parámetros genéricos.
export type FieldMetaAccess = Pick<AnyFormApi, 'state' | 'setFieldMeta'>;

export interface FieldMessage {
  name: string;
  message: string;
}

// Carga un error del servidor (422) o de la tabla de verificación en el campo. undefined lo
// quita: un cambio del usuario deja sin efecto el error del servidor.
export function setServerError(form: FieldMetaAccess, name: string, message: string | undefined): void {
  form.setFieldMeta(name, (meta) => ({ ...meta, errorMap: { ...meta.errorMap, onServer: message } }));
}

// Mensajes por campo de un 422 (Problem Details, ADR-010) para los campos del formulario.
// fieldOf traduce la clave del backend cuando difiere del nombre del campo.
export function serverFieldMessages(
  fieldErrors: Readonly<Record<string, readonly string[]>>,
  fields: readonly string[],
  fieldOf: (key: string) => string = (key) => key,
): FieldMessage[] {
  return Object.entries(fieldErrors).flatMap(([key, messages]) => {
    const name = fieldOf(key);
    const message = messages[0];

    return message === undefined || !fields.includes(name) ? [] : [{ name, message }];
  });
}

// Primer campo con error visible al grabar, en el orden en que se montaron los campos (el de
// la pantalla).
export function firstInvalidField(form: FieldMetaAccess): FieldMessage | undefined {
  for (const [name, meta] of Object.entries(form.state.fieldMeta as Partial<Record<string, unknown>>)) {
    const message = visibleFieldError(meta as FieldErrorState | undefined, true);

    if (message !== undefined) {
      return { name, message };
    }
  }

  return undefined;
}
