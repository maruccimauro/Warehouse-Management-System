import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { GridMessage } from '../grid/gridTypes';
import { Icon } from '../icons';
import { MultipleSelectionDialog } from './MultipleSelectionDialog';
import { mainEntryIndex, parameterValue, selectOptionsOf, withMainEntry } from './selection';
import { SelectionInput } from './SelectionInput';
import type { ParameterField, SelectOptionsField } from './selectionTypes';
import { controlId, selectionErrorText, type SelectionScreenState } from './useSelectionScreen';

interface SelectionScreenProps {
  selection: SelectionScreenState;
  // Título del bloque, como SELECTION-SCREEN BEGIN OF BLOCK ... WITH FRAME TITLE.
  title?: string;
  onMessage: (message: GridMessage) => void;
}

// Pantalla de selección (E2.18, ADR-016): parámetros y criterios con desde, hasta y selección
// múltiple. No es un <form>: Enter confirma el campo (lo valida, como en SAP) y no ejecuta; se
// ejecuta con F8. Además, un <form> envolvería los de los popups F4 que se abren adentro.
export function SelectionScreen({ selection, title, onMessage }: SelectionScreenProps) {
  const titleId = useId();

  return (
    <search
      className="max-w-4xl rounded-sm border border-border bg-surface"
      aria-labelledby={title === undefined ? undefined : titleId}
    >
      {title !== undefined && (
        <h2 id={titleId} className="border-b border-border bg-surface-alt px-3 py-1.5 text-sm font-semibold">
          {title}
        </h2>
      )}
      <div className="grid gap-1.5 px-3 py-2">
        {selection.fields.map((field) =>
          field.kind === 'parameter' ? (
            <ParameterRow key={field.id} field={field} selection={selection} onMessage={onMessage} />
          ) : (
            <SelectOptionsRow key={field.id} field={field} selection={selection} onMessage={onMessage} />
          ),
        )}
      </div>
    </search>
  );
}

const rowClass = 'flex flex-wrap items-center gap-x-3 gap-y-1';
const labelClass = 'w-48 shrink-0 text-ink-muted';
// Ancho fijo de cada límite, para que "hasta" y el botón de selección múltiple queden en
// columna entre campos con y sin F4 (13rem cubre el campo y el botón con puntero grueso).
const limitClass = 'w-52 shrink-0';

interface RowProps<TField> {
  field: TField;
  selection: SelectionScreenState;
  onMessage: (message: GridMessage) => void;
}

function ParameterRow({ field, selection, onMessage }: RowProps<ParameterField>) {
  const { baseId, setValue, setValidity } = selection.screen;
  const inputId = controlId(baseId, field.id, 'low');
  const required = field.required === true;

  return (
    <div className={rowClass} data-help-field={field.id}>
      <label htmlFor={inputId} className={labelClass}>
        {field.label}
        {required && (
          <span aria-hidden="true" className="ms-0.5 text-error">
            *
          </span>
        )}
      </label>
      <SelectionInput
        field={field}
        id={inputId}
        value={parameterValue(selection.values, field.id)}
        check
        required={required}
        onChange={(value, valid) => {
          setValue(field.id, value);
          setValidity(field.id, valid);
        }}
        onMessage={onMessage}
      />
    </div>
  );
}

function SelectOptionsRow({ field, selection, onMessage }: RowProps<SelectOptionsField>) {
  const { t } = useTranslation('selection');
  const { baseId, setValue } = selection.screen;
  const [dialogOpen, setDialogOpen] = useState(false);
  const labelId = `${controlId(baseId, field.id, 'low')}-label`;
  const toId = `${controlId(baseId, field.id, 'high')}-label`;
  const countId = `${controlId(baseId, field.id, 'multiple')}-count`;
  const lockedId = `${controlId(baseId, field.id, 'low')}-locked`;

  const entries = selectOptionsOf(selection.values, field.id);
  const main = entries[mainEntryIndex(entries)];
  const low = main?.low ?? '';
  const high = main?.option === 'BT' ? (main.high ?? '') : '';
  // Hay más de lo que muestran desde y hasta: el botón se rellena, como la flecha verde de SAP,
  // y desde y hasta quedan en solo lectura hasta que la selección múltiple vuelva a una sola
  // entrada simple, para no editar una parte de la selección sin ver el resto.
  const hasMore = entries.length > (main === undefined ? 0 : 1);
  const multipleLabel = t('multiple', { label: field.label });

  return (
    <div className={rowClass} data-help-field={field.id}>
      <label id={labelId} htmlFor={controlId(baseId, field.id, 'low')} className={labelClass}>
        {field.label}
      </label>
      <div className={limitClass}>
        <SelectionInput
          field={field}
          id={controlId(baseId, field.id, 'low')}
          value={low}
          check={false}
          disabled={hasMore}
          aria-describedby={hasMore ? lockedId : undefined}
          onChange={(value) => {
            setValue(field.id, withMainEntry(entries, value, high));
          }}
          onMessage={onMessage}
        />
      </div>
      <span id={toId} className="w-10 text-ink-muted">
        {t('to')}
      </span>
      <div className={limitClass}>
        <SelectionInput
          field={field}
          id={controlId(baseId, field.id, 'high')}
          value={high}
          check={false}
          aria-labelledby={`${labelId} ${toId}`}
          disabled={hasMore}
          aria-describedby={hasMore ? lockedId : undefined}
          onChange={(value) => {
            setValue(field.id, withMainEntry(entries, low, value));
          }}
          onMessage={onMessage}
        />
      </div>
      <button
        id={controlId(baseId, field.id, 'multiple')}
        type="button"
        className={`inline-flex min-h-7 min-w-7 items-center justify-center rounded-sm border pointer-coarse:min-h-11 pointer-coarse:min-w-11 ${
          hasMore ? 'border-success bg-success-soft text-success' : 'border-border-strong text-ink hover:bg-hover'
        }`}
        aria-label={multipleLabel}
        aria-haspopup="dialog"
        aria-describedby={entries.length > 0 ? countId : undefined}
        title={multipleLabel}
        onClick={() => {
          setDialogOpen(true);
        }}
      >
        <Icon name={hasMore ? 'multipleSelectionActive' : 'multipleSelection'} />
      </button>
      {hasMore && (
        <span id={lockedId} className="visually-hidden">
          {t('locked', { label: field.label })}
        </span>
      )}
      {entries.length > 0 && (
        <span id={countId} className={hasMore ? 'text-xs text-success' : 'visually-hidden'}>
          {t('entries', { count: entries.length })}
        </span>
      )}
      <MultipleSelectionDialog
        open={dialogOpen}
        field={field}
        entries={entries}
        onAccept={(next) => {
          setValue(field.id, next);
          setDialogOpen(false);
        }}
        onCancel={() => {
          setDialogOpen(false);
        }}
        onMessage={onMessage}
        errorText={(code) => selectionErrorText(t, code, field.label)}
      />
    </div>
  );
}
