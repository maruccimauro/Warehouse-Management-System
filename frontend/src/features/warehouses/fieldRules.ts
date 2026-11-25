import { z } from 'zod';
import { i18n } from '@/i18n';

// Reglas de los campos de depósitos, zonas y ubicaciones con mensajes propios: los de Zod por
// defecto muestran la expresión regular, que no le dice nada al usuario. El texto se resuelve
// al validar, en el idioma activo.
type RuleKey = 'required' | 'maxLength' | 'code' | 'positiveInteger' | 'aisle' | 'gridNumber';

const message = (key: RuleKey, options?: Record<string, unknown>) => () => i18n.t(`rules.${key}`, { ns: 'locations', ...options });

export const required = () => z.string().trim().min(1, { error: message('required') });

// Código de depósito, zona o ubicación: letras y números, con guiones o guiones bajos entre
// ellos (NormalizesCodes::CODE_PATTERN del backend). Se compara en mayúsculas.
export const codeRule = (max: number) =>
  required()
    .max(max, { error: message('maxLength', { max }) })
    .regex(/^[A-Z0-9]+([_-][A-Z0-9]+)*$/i, { error: message('code') });

export const textRule = (max: number) => z.string().trim().max(max, { error: message('maxLength', { max }) });

// Entero positivo opcional (capacidad en unidades).
export const optionalPositiveInteger = () =>
  z
    .string()
    .trim()
    .regex(/^([1-9]\d*)?$/, { error: message('positiveInteger') });

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
