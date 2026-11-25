import type { TFunction } from 'i18next';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { SelectOption } from '@/shared/api/listing';
import type { GridMessage } from '../grid/gridTypes';
import { checkSelection, initialSelectionValues, selectionMessageIds, type SelectionErrorCode } from './selection';
import type { SelectionField, SelectionValues } from './selectionTypes';

export interface SelectionScreenState {
  fields: readonly SelectionField[];
  values: SelectionValues;
  // Valida como el PAI de SAP al apretar F8: con un error emite el mensaje E, lleva el foco al
  // campo y devuelve undefined; si no, devuelve los valores normalizados y los deja en pantalla.
  validate: (onMessage: (message: GridMessage) => void) => SelectionValues | undefined;
  // Restablece los valores (por ejemplo, al cambiar de variante en el futuro).
  reset: (values?: SelectionValues) => void;
  // Uso interno de SelectionScreen.
  screen: {
    baseId: string;
    setValue: (fieldId: string, value: string | readonly SelectOption[]) => void;
    setValidity: (fieldId: string, valid: boolean) => void;
  };
}

// Estado de una pantalla de selección (E2.18). La transacción lo crea, registra F8 con
// useTransactionActions({ execute }) y llama a validate antes de leer los datos; al mostrar el
// resultado desmonta SelectionScreen pero conserva este estado, así F3 vuelve a la selección
// con lo cargado (D6). Los campos tienen que ser estables (useMemo).
export function useSelectionScreen(fields: readonly SelectionField[], initial?: SelectionValues): SelectionScreenState {
  const { t } = useTranslation('selection');
  const baseId = useId();
  const [values, setValues] = useState(() => initialSelectionValues(fields, initial));
  const [invalid, setInvalid] = useState<ReadonlySet<string>>(() => new Set());

  const setValue = (fieldId: string, value: string | readonly SelectOption[]) => {
    setValues((previous) => ({ ...previous, [fieldId]: value }));
  };

  const setValidity = (fieldId: string, valid: boolean) => {
    setInvalid((previous) => {
      if (previous.has(fieldId) !== valid) {
        return previous;
      }

      const next = new Set(previous);

      if (valid) {
        next.delete(fieldId);
      } else {
        next.add(fieldId);
      }

      return next;
    });
  };

  const validate = (onMessage: (message: GridMessage) => void) => {
    const result = checkSelection(fields, values, invalid);

    if (result.ok) {
      setValues(result.values);

      return result.values;
    }

    const { fieldId, target, code } = result.error;
    const field = fields.find((candidate) => candidate.id === fieldId);

    onMessage({ type: 'E', text: selectionErrorText(t, code, field?.label ?? fieldId), id: selectionMessageIds[code] });
    document.getElementById(controlId(baseId, fieldId, target))?.focus();

    return undefined;
  };

  return {
    fields,
    values,
    validate,
    reset: (next) => {
      setValues(initialSelectionValues(fields, next));
      setInvalid(new Set());
    },
    screen: { baseId, setValue, setValidity },
  };
}

export function selectionErrorText(t: TFunction<'selection'>, code: SelectionErrorCode, label: string): string {
  return code === 'required' ? t('errors.required') : t(`errors.${code}`, { label });
}

export function controlId(baseId: string, fieldId: string, part: 'low' | 'high' | 'multiple'): string {
  return `${baseId}-${fieldId}-${part}`;
}
