import type { RowData } from '@tanstack/react-table';
import { useEffect, useId, useRef, useState, type FocusEvent, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { GridMessage } from '../grid/gridTypes';
import { Icon } from '../icons';
import { SearchHelpDialog } from './SearchHelpDialog';
import type { SearchHelp } from './searchHelpTypes';

export interface SearchHelpFieldProps<TData> {
  // id del <input>, para asociarle el <label> de la pantalla.
  id: string;
  // Valor del campo ('' sin valor).
  value: string;
  // Se llama con cada cambio de lo escrito, ya normalizado, y al tomar un valor del F4. valid
  // en false: el texto no existe en la ayuda; la pantalla no graba hasta que se corrija (D3 de
  // E2.17). Avisar en cada cambio, y no solo al salir del campo, evita que Ctrl+S con el foco
  // en el campo grabe el valor anterior: como el PAI de SAP, toda acción ve lo escrito.
  onChange: (value: string, valid: boolean) => void;
  searchHelp: SearchHelp<TData>;
  onMessage: (message: GridMessage) => void;
  disabled?: boolean;
  className?: string;
  'aria-describedby'?: string;
}

// Campo con ayuda de búsqueda (E2.17, ADR-016). Como en SAP: F4 o el botón abren el popup,
// lo escrito se valida contra las filas de la ayuda (la tabla de verificación) y a la derecha
// se muestra el texto del valor (D4). Al confirmar (Enter o salir del campo) un valor
// inexistente queda marcado y emite un mensaje E. F4 lo maneja el campo y no el armazón (D7):
// es la ayuda del campo con foco, no una acción de la transacción.
export function SearchHelpField<TData extends RowData>({
  id,
  value,
  onChange,
  searchHelp,
  onMessage,
  disabled = false,
  className = '',
  'aria-describedby': describedBy,
}: SearchHelpFieldProps<TData>) {
  const { t } = useTranslation('searchHelp');
  const descriptionId = useId();
  const wrapperRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const wasOpen = useRef(false);
  const [text, setText] = useState(value);
  const [invalid, setInvalid] = useState(false);
  // Último texto confirmado: salir del campo sin cambios no repite el mensaje E.
  const [committed, setCommitted] = useState(value);
  const [open, setOpen] = useState(false);
  const [initialCriterion, setInitialCriterion] = useState('');
  const [syncedValue, setSyncedValue] = useState(value);

  const normalize = searchHelp.normalize ?? ((raw: string) => raw.trim());
  const rowOf = (candidate: string) => searchHelp.source.rows.find((row) => searchHelp.valueOf(row) === candidate);
  const isValid = (candidate: string) => candidate === '' || rowOf(candidate) !== undefined;

  // Un valor que llega desde afuera (restablecido por la pantalla) reemplaza lo escrito. El
  // que este campo acaba de informar no: el texto puede diferir solo por la normalización.
  if (syncedValue !== value) {
    setSyncedValue(value);

    if (value !== normalize(text)) {
      setText(value);
      setInvalid(false);
      setCommitted(value);
    }
  }

  // Al cerrar el popup el foco vuelve al campo, también si se abrió con el botón (D7).
  useEffect(() => {
    if (wasOpen.current && !open) {
      inputRef.current?.focus();
    }

    wasOpen.current = open;
  }, [open]);

  const commit = () => {
    const normalized = normalize(text);

    setText(normalized);

    if (normalized === committed) {
      return;
    }

    setCommitted(normalized);

    if (isValid(normalized)) {
      setInvalid(false);

      return;
    }

    setInvalid(true);
    onMessage({ type: 'E', text: searchHelp.invalidMessage(normalized) });
  };

  const openHelp = () => {
    if (disabled) {
      return;
    }

    // Lo escrito que no es un valor válido restringe el primer criterio (D2).
    const normalized = normalize(text);

    setInitialCriterion(isValid(normalized) ? '' : text.trim());
    setOpen(true);
  };

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'F4' && !event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
      event.preventDefault();
      openHelp();
    } else if (event.key === 'Enter') {
      commit();
    }
  };

  const onBlur = (event: FocusEvent<HTMLInputElement>) => {
    // Pasar al botón F4 (con el puntero) o al popup no valida: la ayuda se abre sobre lo escrito.
    if (open || (event.relatedTarget instanceof Node && wrapperRef.current?.contains(event.relatedTarget) === true)) {
      return;
    }

    commit();
  };

  const select = (row: TData) => {
    const selected = searchHelp.valueOf(row);

    setText(selected);
    setInvalid(false);
    setCommitted(selected);
    setOpen(false);
    onChange(selected, true);
  };

  const currentRow = rowOf(normalize(text));
  const description = currentRow === undefined ? '' : searchHelp.descriptionOf(currentRow);
  const describedByIds = [describedBy, description === '' ? undefined : descriptionId].filter(Boolean).join(' ');
  const openLabel = t('open', { title: searchHelp.title });

  return (
    <div ref={wrapperRef} className={`flex min-w-0 items-center gap-2 ${className}`}>
      <div className="flex shrink-0 items-center">
        <input
          ref={inputRef}
          id={id}
          className="field-input w-40 rounded-e-none"
          autoComplete="off"
          disabled={disabled}
          aria-invalid={invalid || undefined}
          aria-describedby={describedByIds === '' ? undefined : describedByIds}
          aria-keyshortcuts="F4"
          value={text}
          onChange={(event) => {
            const next = event.target.value;
            const normalized = normalize(next);

            setText(next);
            setInvalid(false);
            setSyncedValue(normalized);
            onChange(normalized, isValid(normalized));
          }}
          onKeyDown={onKeyDown}
          onBlur={onBlur}
        />
        <button
          type="button"
          className="inline-flex min-h-7 min-w-7 items-center justify-center rounded-e-sm border border-s-0 border-border-strong bg-surface hover:bg-hover disabled:cursor-not-allowed disabled:opacity-60 pointer-coarse:min-h-11 pointer-coarse:min-w-11"
          aria-label={openLabel}
          aria-haspopup="dialog"
          aria-keyshortcuts="F4"
          // Fuera del orden de Tab, como en SAP: con teclado la ayuda se abre con F4 y Tab pasa
          // directo al campo siguiente, validando lo escrito.
          tabIndex={-1}
          title={openLabel}
          disabled={disabled}
          onClick={openHelp}
        >
          <Icon name="searchHelp" />
        </button>
      </div>
      {description !== '' && (
        <span id={descriptionId} className="min-w-0 truncate text-ink-muted">
          {description}
        </span>
      )}
      <SearchHelpDialog
        open={open}
        searchHelp={searchHelp}
        initialCriterion={initialCriterion}
        currentValue={currentRow === undefined ? undefined : searchHelp.valueOf(currentRow)}
        onSelect={select}
        onCancel={() => {
          setOpen(false);
        }}
        onMessage={onMessage}
      />
    </div>
  );
}
