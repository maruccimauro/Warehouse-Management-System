import { z } from 'zod';
import { i18n } from '@/i18n';

// Reglas de campo con mensajes propios (E2.12, compartidas desde E2.13): los de Zod por defecto
// muestran la expresión regular, que no le dice nada al usuario. El texto se resuelve al
// validar, en el idioma activo. Las reglas anticipan las del backend; el servidor sigue
// validando y sus 422 se muestran en cada campo.
type RuleKey =
  | 'required'
  | 'maxLength'
  | 'code'
  | 'positiveInteger'
  | 'nonNegativeInteger'
  | 'integerMax'
  | 'aisle'
  | 'gridNumber'
  | 'email'
  | 'printable'
  | 'taxId';

const message = (key: RuleKey, options?: Record<string, unknown>) => () => i18n.t(`rules.${key}`, { ns: 'form', ...options });

export const required = () => z.string().trim().min(1, { error: message('required') });

// Código de depósito, zona, ubicación o SKU: letras y números, con guiones o guiones bajos
// entre ellos (NormalizesCodes::CODE_PATTERN del backend). Se compara en mayúsculas.
export const codeRule = (max: number) =>
  required()
    .max(max, { error: message('maxLength', { max }) })
    .regex(/^[A-Z0-9]+([_-][A-Z0-9]+)*$/i, { error: message('code') });

export const textRule = (max: number) => z.string().trim().max(max, { error: message('maxLength', { max }) });

export const requiredText = (max: number) => required().max(max, { error: message('maxLength', { max }) });

// Entero positivo opcional (capacidad en unidades, dimensiones del producto). Sin max, hasta el
// máximo de una columna INTEGER.
export const optionalPositiveInteger = (max = 2147483647) =>
  z
    .string()
    .trim()
    .regex(/^([1-9]\d*)?$/, { error: message('positiveInteger') })
    .refine((text) => text === '' || Number(text) <= max, { error: message('integerMax', { max }) });

// Entero desde cero, opcional u obligatorio (stock mínimo, plazo de entrega en días).
export const nonNegativeInteger = (max: number, { optional = false } = {}) =>
  z
    .string()
    .trim()
    .refine((text) => (optional && text === '') || /^\d+$/.test(text), { error: message('nonNegativeInteger') })
    .refine((text) => text === '' || Number(text) <= max, { error: message('integerMax', { max }) });

// Pasillo de la plantilla: una letra.
export const aisleRule = () =>
  z
    .string()
    .trim()
    .regex(/^[A-Za-z]$/, { error: message('aisle') });

// Estantería, nivel o posición de la plantilla: de 1 a max.
export const gridNumberRule = (max: number) =>
  z
    .string()
    .trim()
    .refine((text) => /^\d{1,2}$/.test(text) && Number(text) >= 1 && Number(text) <= max, { error: message('gridNumber', { max }) });

// Email opcional.
export const optionalEmail = (max: number) =>
  textRule(max).refine((text) => text === '' || z.email().safeParse(text).success, { error: message('email') });

// Código de barras opcional: caracteres imprimibles sin espacios (Barcode del backend). El
// dígito verificador de un GTIN lo controla el servidor.
export const optionalPrintable = (max: number) =>
  textRule(max).regex(/^[!-~]*$/, { error: message('printable') });

// Identificador fiscal: se compara sin espacios, puntos, guiones ni barras y en mayúsculas
// (Supplier::normalizeTaxId del backend); quedan de 5 a 20 letras o números.
export function normalizeTaxId(text: string): string {
  return text.replaceAll(/[\s./-]+/gu, '').toUpperCase();
}

export const taxIdRule = () =>
  required().refine((text) => /^[A-Z0-9]{5,20}$/.test(normalizeTaxId(text)), { error: message('taxId') });
