import type { RowData } from '@tanstack/react-table';
import { useEffect, useId, useLayoutEffect, useRef, useState, type FocusEvent, type KeyboardEvent } from 'react';
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
  // en false: el texto no existe en la ayuda o la ayuda lo rechaza (problem trae el mensaje);
  // la pantalla no graba hasta que se corrija (D3 de E2.17). Avisar en cada cambio, y no solo al salir del campo, evita que Ctrl+S con el foco
  // en el campo grabe el valor anterior: como el PAI de SAP, toda acción ve lo escrito.
  onChange: (value: string, valid: boolean, problem?: GridMessage) => void;
  searchHelp: SearchHelp<TData>;
  onMessage: (message: GridMessage) => void;
  // Al salir del campo, después de validarlo (el formulario marca el campo como visitado).
  onBlur?: (() => void) | undefined;
  disabled?: boolean | undefined;
  // false: sin tabla de verificación, como un límite de SELECT-OPTIONS (D4 de E2.18). Ofrece F4
  // y la descripción, pero cualquier valor es válido: un rango de A a M no exige que A exista.
  check?: boolean | undefined;
  required?: boolean | undefined;
  className?: string;
  'aria-describedby'?: string | undefined;
  // Nombre accesible cuando no hay un <label> propio (una celda de una tabla de entradas).
  'aria-labelledby'?: string | undefined;
  // Error que informa el formulario (esquema o servidor), además del de la tabla de verificación.
  'aria-invalid'?: boolean | undefined;
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
  onBlur: onBlurProp,
  disabled = false,
  check = true,
  required = false,
  className = '',
  'aria-describedby': describedBy,
  'aria-labelledby': labelledBy,
  'aria-invalid': formInvalid = false,
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
  const localRows = searchHelp.source.kind === 'local' ? searchHelp.source.rows : undefined;
  // Filas todavía en camino (las zonas del depósito recién elegido): lo escrito no se rechaza
  // hasta tenerlas; al llegar se verifica y, si ya se había confirmado, se informa el error.
  const loading = searchHelp.source.kind === 'local' && searchHelp.source.loading === true;
  const rowOf = (candidate: string) => localRows?.find((row) => searchHelp.valueOf(row) === candidate);
  const exists = (candidate: string) => candidate === '' || rowOf(candidate) !== undefined;
  // Con fuente de servidor el campo no tiene tabla de verificación: la pantalla valida el valor
  // al leer el registro. Un valor inexistente da VALUE_NOT_FOUND; uno que existe puede tener
  // su propio rechazo (rejectionOf).
  const problemOf = (candidate: string): GridMessage | undefined => {
    if (!check || localRows === undefined || loading || candidate === '') {
      return undefined;
    }

    const row = rowOf(candidate);

    if (row === undefined) {
      return { type: 'E', text: searchHelp.invalidMessage(candidate), id: 'VALUE_NOT_FOUND' };
    }

    return searchHelp.rejectionOf?.(row);
  };
  // A la pantalla solo le llega el mensaje de un rechazo: el de un valor inexistente lo arma ella
  // con la etiqueta del campo (VALUE_NOT_FOUND, "el valor no existe").
  const rejection = (problem: GridMessage | undefined) => (problem?.id === 'VALUE_NOT_FOUND' ? undefined : problem);

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

  // Las filas pueden llegar después de lo escrito (las zonas del depósito recién elegido): con
  // filas nuevas se vuelve a verificar el valor y se informa a la pantalla si cambió su validez.
  const rowsSeen = useRef({ rows: localRows, loading });
  const latestCheck = useRef({ text, committed, normalize, problemOf, onChange, onMessage });

  useLayoutEffect(() => {
    latestCheck.current = { text, committed, normalize, problemOf, onChange, onMessage };
  });

  useEffect(() => {
    if ((rowsSeen.current.rows === localRows && rowsSeen.current.loading === loading) || loading) {
      return;
    }

    rowsSeen.current = { rows: localRows, loading };

    const latest = latestCheck.current;
    const current = latest.normalize(latest.text);

    if (current === '') {
      return;
    }

    const problem = latest.problemOf(current);

    setInvalid(problem !== undefined && current === latest.committed);
    latest.onChange(current, problem === undefined, rejection(problem));

    if (problem !== undefined && current === latest.committed) {
      latest.onMessage(problem);
    }
  }, [localRows, loading]);

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

    const problem = problemOf(normalized);

    setInvalid(problem !== undefined);

    if (problem !== undefined) {
      onMessage(problem);
    }
  };

  const openHelp = () => {
    if (disabled) {
      return;
    }

    // Lo escrito que no es un valor válido restringe el primer criterio (D2).
    const normalized = normalize(text);

    setInitialCriterion(localRows !== undefined && exists(normalized) ? '' : text.trim());
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
    onBlurProp?.();
  };

  const select = (row: TData) => {
    const selected = searchHelp.valueOf(row);

    // El F4 ofrece también filas que el campo rechaza (un depósito en el que no se opera): se
    // toman y se informan como al escribirlas.
    const problem = searchHelp.rejectionOf?.(row);

    setText(selected);
    setInvalid(problem !== undefined);
    setCommitted(selected);
    setOpen(false);
    onChange(selected, problem === undefined, problem);

    if (problem !== undefined) {
      onMessage(problem);
    }
  };

  const currentRow = rowOf(normalize(text));
  const description = currentRow === undefined ? '' : searchHelp.descriptionOf(currentRow);
  const describedByIds = [describedBy, description === '' ? undefined : descriptionId].filter(Boolean).join(' ');
  const openLabel = t('open', { title: searchHelp.title });

  return (
    <div ref={wrapperRef} className={`flex min-w-0 items-center gap-2 ${className}`}>
      <div className="field-group shrink-0">
        <input
          ref={inputRef}
          id={id}
          className="w-40"
          autoComplete="off"
          disabled={disabled}
          required={required}
          aria-labelledby={labelledBy}
          aria-invalid={invalid || formInvalid || undefined}
          aria-describedby={describedByIds === '' ? undefined : describedByIds}
          aria-keyshortcuts="F4"
          value={text}
          onChange={(event) => {
            const next = event.target.value;
            const normalized = normalize(next);

            setText(next);
            setInvalid(false);
            setSyncedValue(normalized);
            const problem = problemOf(normalized);

            onChange(normalized, problem === undefined, rejection(problem));
          }}
          onKeyDown={onKeyDown}
          onBlur={onBlur}
        />
        <button
          type="button"
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
        currentValue={localRows === undefined ? normalize(text) || undefined : currentRow === undefined ? undefined : searchHelp.valueOf(currentRow)}
        onSelect={select}
        onCancel={() => {
          setOpen(false);
        }}
        onMessage={onMessage}
      />
    </div>
  );
}
