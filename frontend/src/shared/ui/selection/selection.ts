import { optionHasHigh, type ListFilterValue, type SelectOption } from '@/shared/api/listing';
import type { SelectionField, SelectionValues, SelectOptionsField } from './selectionTypes';

// Lógica de la pantalla de selección (E2.18) sin React: leer y escribir valores, validar antes
// de ejecutar (el PAI de SAP al apretar F8) y traducir los criterios a los filtros del listado.

export type SelectionPart = 'low' | 'high';

export type SelectionErrorCode = 'required' | 'invalid' | 'integer' | 'lowMissing' | 'lowGreater';

export interface SelectionError {
  fieldId: string;
  // Qué control recibe el foco: el límite del renglón de la pantalla, o el botón de selección
  // múltiple si el error está en una entrada que solo se ve en el diálogo.
  target: SelectionPart | 'multiple';
  code: SelectionErrorCode;
}

export type SelectionCheck = { ok: true; values: SelectionValues } | { ok: false; error: SelectionError };

const integerPattern = /^-?\d+$/;

export function parameterValue(values: SelectionValues, fieldId: string): string {
  const value = values[fieldId];

  return typeof value === 'string' ? value : '';
}

export function selectOptionsOf(values: SelectionValues, fieldId: string): readonly SelectOption[] {
  const value = values[fieldId];

  return typeof value === 'string' || value === undefined ? [] : value;
}

// Valores iniciales: lo indicado por la pantalla y vacío en el resto.
export function initialSelectionValues(
  fields: readonly SelectionField[],
  initial: SelectionValues = {},
): SelectionValues {
  return Object.fromEntries(
    fields.map((field) => [
      field.id,
      field.kind === 'parameter' ? parameterValue(initial, field.id) : selectOptionsOf(initial, field.id),
    ]),
  );
}

// La entrada que muestran desde y hasta en la pantalla: la primera que incluye un valor o un
// rango, como en SAP. Las demás se ven en el diálogo de selección múltiple.
export function mainEntryIndex(entries: readonly SelectOption[]): number {
  return entries.findIndex((entry) => entry.sign === 'I' && (entry.option === 'EQ' || entry.option === 'BT'));
}

// Escribe desde y hasta en la entrada principal: con hasta es un rango (BT) y sin él un valor
// (EQ). Vaciar los dos quita la entrada.
export function withMainEntry(entries: readonly SelectOption[], low: string, high: string): SelectOption[] {
  const index = mainEntryIndex(entries);

  if (low === '' && high === '') {
    return entries.filter((_, position) => position !== index);
  }

  const entry: SelectOption =
    high === '' ? { sign: 'I', option: 'EQ', low } : { sign: 'I', option: 'BT', low, high };

  return index === -1 ? [entry, ...entries] : entries.map((current, position) => (position === index ? entry : current));
}

function normalizeText(field: SelectionField, text: string): string {
  return field.normalize === undefined ? text.trim() : field.normalize(text);
}

// Error de una entrada con sus límites ya normalizados. Solo los enteros comparan límites: el
// orden del texto depende de la intercalación de la base, que el navegador no conoce.
export function entryError(
  field: SelectionField,
  entry: Pick<SelectOption, 'option' | 'low' | 'high'>,
): { part: SelectionPart; code: SelectionErrorCode } | undefined {
  const high = optionHasHigh(entry.option) ? (entry.high ?? '') : '';

  if (entry.low === '' && high !== '') {
    return { part: 'low', code: 'lowMissing' };
  }

  if (field.type === 'integer') {
    if (!integerPattern.test(entry.low)) {
      return { part: 'low', code: 'integer' };
    }

    if (high !== '' && !integerPattern.test(high)) {
      return { part: 'high', code: 'integer' };
    }

    if (high !== '' && Number(entry.low) > Number(high)) {
      return { part: 'high', code: 'lowGreater' };
    }
  }

  return undefined;
}

// Normaliza las entradas de un SELECT-OPTIONS: recorta, aplica la normalización del campo y
// descarta las vacías. Un rango sin hasta pasa a valor único, como en SAP.
export function normalizeEntries(field: SelectOptionsField, entries: readonly SelectOption[]): SelectOption[] {
  return entries.flatMap((entry): SelectOption[] => {
    const low = normalizeText(field, entry.low);
    const high = optionHasHigh(entry.option) ? normalizeText(field, entry.high ?? '') : '';

    if (low === '' && high === '') {
      return [];
    }

    if (optionHasHigh(entry.option)) {
      return high === ''
        ? [{ sign: entry.sign, option: entry.option === 'BT' ? 'EQ' : 'NE', low }]
        : [{ sign: entry.sign, option: entry.option, low, high }];
    }

    return [{ sign: entry.sign, option: entry.option, low }];
  });
}

// Validación al ejecutar (F8): obligatorios, valores inexistentes en la tabla de verificación
// (invalid, informado por los campos `as`), enteros y límites. Devuelve los valores
// normalizados o el primer error en el orden de la pantalla.
export function checkSelection(
  fields: readonly SelectionField[],
  values: SelectionValues,
  invalid: ReadonlySet<string>,
): SelectionCheck {
  const checked: Record<string, string | SelectOption[]> = {};

  for (const field of fields) {
    if (field.kind === 'parameter') {
      const value = normalizeText(field, parameterValue(values, field.id));

      if (value === '' && field.required === true) {
        return { ok: false, error: { fieldId: field.id, target: 'low', code: 'required' } };
      }

      if (value !== '' && invalid.has(field.id)) {
        return { ok: false, error: { fieldId: field.id, target: 'low', code: 'invalid' } };
      }

      if (value !== '' && field.type === 'integer' && !integerPattern.test(value)) {
        return { ok: false, error: { fieldId: field.id, target: 'low', code: 'integer' } };
      }

      checked[field.id] = value;
      continue;
    }

    const entries = normalizeEntries(field, selectOptionsOf(values, field.id));
    const main = mainEntryIndex(entries);

    for (const [index, entry] of entries.entries()) {
      const error = entryError(field, entry);

      if (error !== undefined) {
        return {
          ok: false,
          error: { fieldId: field.id, target: index === main ? error.part : 'multiple', code: error.code },
        };
      }
    }

    checked[field.id] = entries;
  }

  return { ok: true, values: checked };
}

// Filtros del listado (ListRequest) para los SELECT-OPTIONS con entradas. Los parámetros no se
// incluyen: suelen ser parte de la ruta (el depósito de /warehouses/{id}/locations) y cada
// pantalla decide cómo los usa.
export function selectionFilters(
  fields: readonly SelectionField[],
  values: SelectionValues,
): Record<string, ListFilterValue> {
  const filters: Record<string, ListFilterValue> = {};

  for (const field of fields) {
    if (field.kind === 'selectOptions') {
      const entries = selectOptionsOf(values, field.id);

      if (entries.length > 0) {
        filters[field.field ?? field.id] = entries;
      }
    }
  }

  return filters;
}
