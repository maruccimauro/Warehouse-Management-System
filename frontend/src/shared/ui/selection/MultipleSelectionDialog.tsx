import { useId, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { SelectionOptionCode, SelectOption } from '@/shared/api/listing';
import { Button } from '../Button';
import { GridDialog } from '../grid/GridDialog';
import type { GridMessage } from '../grid/gridTypes';
import { Icon } from '../icons';
import { entryError, normalizeEntries, selectionMessageIds, type SelectionErrorCode } from './selection';
import { SelectionInput } from './SelectionInput';
import type { SelectOptionsField } from './selectionTypes';

interface MultipleSelectionDialogProps {
  open: boolean;
  field: SelectOptionsField;
  entries: readonly SelectOption[];
  onAccept: (entries: SelectOption[]) => void;
  onCancel: () => void;
  onMessage: (message: GridMessage) => void;
  errorText: (code: SelectionErrorCode) => string;
}

// Al abrir, el foco va al primer campo de la pestaña visible.
const firstVisibleInput = (dialog: HTMLDialogElement) =>
  dialog.querySelector<HTMLElement>('[role="tabpanel"]:not([hidden]) input');

// Selección múltiple de un SELECT-OPTIONS (D3 de E2.18), con las cuatro pestañas de SAP:
// valores individuales y rangos que se incluyen, y sus exclusiones. Las de exclusión se ven
// deshabilitadas hasta después del MVP (ADR-016); el modelo y el backend ya las admiten.
export function MultipleSelectionDialog({
  open,
  field,
  entries,
  onAccept,
  onCancel,
  onMessage,
  errorText,
}: MultipleSelectionDialogProps) {
  const { t } = useTranslation('selection');

  return (
    <GridDialog
      open={open}
      size="wide"
      title={t('dialog.title', { label: field.label })}
      onCancel={onCancel}
      initialFocus={firstVisibleInput}
    >
      {open && (
        <MultipleSelectionForm
          field={field}
          entries={entries}
          onAccept={onAccept}
          onCancel={onCancel}
          onMessage={onMessage}
          errorText={errorText}
        />
      )}
    </GridDialog>
  );
}

type TabId = 'single' | 'ranges' | 'excludeSingle' | 'excludeRanges';

const tabs: readonly { id: TabId; enabled: boolean }[] = [
  { id: 'single', enabled: true },
  { id: 'ranges', enabled: true },
  { id: 'excludeSingle', enabled: false },
  { id: 'excludeRanges', enabled: false },
];

interface DraftRow {
  key: string;
  // Condición de un valor individual; los rangos son siempre "entre" (BT).
  option: SingleOption;
  low: string;
  high: string;
}

// Condiciones de un valor individual, en palabras en el diálogo. Con * en el valor, igual a y
// distinto de pasan a patrón (normalizeEntries).
const singleOptions = ['EQ', 'NE', 'GT', 'GE', 'LT', 'LE'] as const;
type SingleOption = (typeof singleOptions)[number];

function singleOptionOf(option: SelectionOptionCode): SingleOption | undefined {
  if (option === 'CP') {
    return 'EQ';
  }

  if (option === 'NP') {
    return 'NE';
  }

  return (singleOptions as readonly string[]).includes(option) ? (option as SingleOption) : undefined;
}

// focusgroup aún no está en los tipos de React: se pasa como atributo sin tipar.
const focusGroupAttribute = { focusgroup: 'tablist nomemory' } as Record<string, string>;
const focusGroupStart = { focusgroupstart: '' } as Record<string, string>;
const nativeFocusGroup = typeof HTMLElement !== 'undefined' && 'focusGroup' in HTMLElement.prototype;

function MultipleSelectionForm({
  field,
  entries,
  onAccept,
  onCancel,
  onMessage,
  errorText,
}: Omit<MultipleSelectionDialogProps, 'open'>) {
  const { t } = useTranslation('selection');
  const id = useId();
  const containerRef = useRef<HTMLDivElement>(null);

  const [selected, setSelected] = useState<TabId>('single');
  const [singles, setSingles] = useState<DraftRow[]>(() =>
    entries.flatMap((entry, index) => {
      const option = entry.sign === 'I' ? singleOptionOf(entry.option) : undefined;

      return option === undefined ? [] : [{ key: `entry-${String(index)}`, option, low: entry.low, high: '' }];
    }),
  );
  const [ranges, setRanges] = useState<DraftRow[]>(() =>
    entries
      .filter((entry) => entry.sign === 'I' && entry.option === 'BT')
      .map((entry, index) => ({ key: `entry-${String(index)}`, option: 'EQ' as const, low: entry.low, high: entry.high ?? '' })),
  );
  // Entradas que estas pestañas no editan (las exclusiones): se conservan tal cual, salvo que
  // se borre todo.
  const [others, setOthers] = useState(() =>
    entries.filter((entry) => !(entry.sign === 'I' && (entry.option === 'BT' || singleOptionOf(entry.option) !== undefined))),
  );

  const counts: Record<TabId, number> = {
    single: singles.filter((row) => row.low.trim() !== '').length,
    ranges: ranges.filter((row) => row.low.trim() !== '' || row.high.trim() !== '').length,
    excludeSingle: 0,
    excludeRanges: 0,
  };

  const focusInput = (inputId: string) => {
    document.getElementById(inputId)?.focus();
  };

  const accept = () => {
    for (const [index, row] of ranges.entries()) {
      const [normalized] = normalizeEntries(field, [{ sign: 'I', option: 'BT', low: row.low, high: row.high }]);
      const error = normalized === undefined ? undefined : entryError(field, normalized);

      if (error !== undefined) {
        setSelected('ranges');
        onMessage({ type: 'E', text: errorText(error.code), id: selectionMessageIds[error.code] });
        // El panel recién elegido se muestra en el próximo render.
        requestAnimationFrame(() => {
          focusInput(`${id}-ranges-${String(index)}-${error.part}`);
        });

        return;
      }
    }

    for (const [index, row] of singles.entries()) {
      const [normalized] = normalizeEntries(field, [{ sign: 'I', option: row.option, low: row.low }]);
      const error = normalized === undefined ? undefined : entryError(field, normalized);

      if (error !== undefined) {
        setSelected('single');
        onMessage({ type: 'E', text: errorText(error.code), id: selectionMessageIds[error.code] });
        requestAnimationFrame(() => {
          focusInput(`${id}-single-${String(index)}-low`);
        });

        return;
      }
    }

    onAccept([
      ...normalizeEntries(
        field,
        singles.map((row) => ({ sign: 'I', option: row.option, low: row.low })),
      ),
      ...normalizeEntries(
        field,
        ranges.map((row) => ({ sign: 'I', option: 'BT', low: row.low, high: row.high })),
      ),
      ...others,
    ]);
  };

  // F8 aplica las entradas, como en el popup de SAP. El armazón no ejecuta F8 con un popup
  // abierto; una ayuda F4 abierta encima la maneja ella.
  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== 'F8' || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
      return;
    }

    const ownDialog = containerRef.current?.closest('dialog');

    if (event.target instanceof Element && event.target.closest('dialog') === ownDialog) {
      event.preventDefault();
      accept();
    }
  };

  const enabledTabs = tabs.filter((tab) => tab.enabled);

  // Respaldo de focusgroup para navegadores sin soporte nativo: la selección sigue al foco.
  const onTabKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (nativeFocusGroup) {
      return;
    }

    const index = enabledTabs.findIndex((tab) => tab.id === selected);
    const rtl = getComputedStyle(event.currentTarget).direction === 'rtl';
    let next: number | undefined;

    if (event.key === (rtl ? 'ArrowLeft' : 'ArrowRight')) {
      next = (index + 1) % enabledTabs.length;
    } else if (event.key === (rtl ? 'ArrowRight' : 'ArrowLeft')) {
      next = (index - 1 + enabledTabs.length) % enabledTabs.length;
    } else if (event.key === 'Home') {
      next = 0;
    } else if (event.key === 'End') {
      next = enabledTabs.length - 1;
    }

    const target = next === undefined ? undefined : enabledTabs[next];

    if (target !== undefined) {
      event.preventDefault();
      setSelected(target.id);
      document.getElementById(`${id}-tab-${target.id}`)?.focus();
    }
  };

  return (
    <div ref={containerRef} onKeyDown={onKeyDown}>
      <div className="px-4 pt-3">
        <div
          role="tablist"
          aria-label={t('dialog.tabs')}
          className="flex overflow-x-auto border-b border-border"
          {...focusGroupAttribute}
        >
          {tabs.map((tab) => {
            const isSelected = tab.id === selected;

            return (
              <button
                key={tab.id}
                id={`${id}-tab-${tab.id}`}
                type="button"
                role="tab"
                aria-selected={isSelected}
                disabled={!tab.enabled}
                title={tab.enabled ? undefined : t('dialog.excludeUnavailable')}
                tabIndex={nativeFocusGroup ? undefined : isSelected ? 0 : -1}
                className="selection-tab"
                onFocus={() => {
                  setSelected(tab.id);
                }}
                onClick={() => {
                  setSelected(tab.id);
                }}
                onKeyDown={onTabKeyDown}
                {...(isSelected ? focusGroupStart : {})}
              >
                {t(`dialog.${tab.id}`)}
                {counts[tab.id] > 0 && (
                  <>
                    {' '}
                    <span className="text-ink-muted">{t('dialog.count', { count: counts[tab.id] })}</span>
                  </>
                )}
              </button>
            );
          })}
        </div>
      </div>
      <div className="max-h-[50dvh] overflow-auto px-4 py-3">
        <EntriesPanel
          id={id}
          tab="single"
          hidden={selected !== 'single'}
          field={field}
          rows={singles}
          withHigh={false}
          onRowsChange={setSingles}
          onMessage={onMessage}
        />
        <EntriesPanel
          id={id}
          tab="ranges"
          hidden={selected !== 'ranges'}
          field={field}
          rows={ranges}
          withHigh
          onRowsChange={setRanges}
          onMessage={onMessage}
        />
      </div>
      <div className="flex flex-wrap justify-end gap-2 border-t border-border px-4 py-2">
        <Button variant="primary" aria-keyshortcuts="F8" title={`${t('dialog.accept')} (F8)`} onClick={accept}>
          {t('dialog.accept')}
        </Button>
        <Button
          onClick={() => {
            if (selected === 'single') {
              setSingles([]);
            } else {
              setRanges([]);
            }
          }}
        >
          {t('dialog.deleteTab')}
        </Button>
        <Button
          onClick={() => {
            setSingles([]);
            setRanges([]);
            setOthers([]);
          }}
        >
          {t('dialog.deleteAll')}
        </Button>
        <Button onClick={onCancel}>{t('dialog.cancel')}</Button>
      </div>
    </div>
  );
}

interface EntriesPanelProps {
  id: string;
  tab: 'single' | 'ranges';
  hidden: boolean;
  field: SelectOptionsField;
  rows: readonly DraftRow[];
  withHigh: boolean;
  onRowsChange: (rows: DraftRow[]) => void;
  onMessage: (message: GridMessage) => void;
}

// Tabla de entradas de una pestaña. Siempre hay una fila vacía al final: escribir en ella
// agrega la entrada, sin un botón de agregar. Cada fila lleva su condición en palabras (en los
// rangos, "entre ... y ...") y el botón de borrar pegado a los campos; la fila bajo el puntero
// se resalta para que quede claro cuál se borra.
function EntriesPanel({ id, tab, hidden, field, rows, withHigh, onRowsChange, onMessage }: EntriesPanelProps) {
  const { t } = useTranslation('selection');
  // La fila vacía conserva su clave al recibir lo escrito, así el campo no se vuelve a montar
  // y no pierde el foco; la nueva fila vacía toma otra.
  const nextKey = useRef(0);
  const [trailingKey, setTrailingKey] = useState('new');
  const shown: DraftRow[] = [...rows, { key: trailingKey, option: 'EQ', low: '', high: '' }];
  const operatorHeaderId = `${id}-${tab}-operator-header`;
  const lowHeaderId = `${id}-${tab}-low-header`;
  const highHeaderId = `${id}-${tab}-high-header`;

  const update = (index: number, patch: Partial<Pick<DraftRow, 'option' | 'low' | 'high'>>) => {
    if (index === rows.length) {
      // Elegir la condición en la fila vacía también la agrega; si queda sin valor, aplicar la
      // descarta (normalizeEntries).
      onRowsChange([...rows, { key: trailingKey, option: 'EQ', low: '', high: '', ...patch }]);

      nextKey.current += 1;
      setTrailingKey(`new-${String(nextKey.current)}`);

      return;
    }

    onRowsChange(rows.map((row, position) => (position === index ? { ...row, ...patch } : row)));
  };

  return (
    <div role="tabpanel" aria-labelledby={`${id}-tab-${tab}`} hidden={hidden}>
      <table className="border-collapse">
        <thead className="bg-toolbar text-xs text-ink-muted">
          <tr>
            <th scope="col" className="w-10 px-2 py-1 text-end">
              <span className="visually-hidden">{t('dialog.rowHeader')}</span>
            </th>
            <th scope="col" id={operatorHeaderId} className="px-2 py-1 text-start">
              {t('dialog.operator')}
            </th>
            <th scope="col" id={lowHeaderId} className="px-2 py-1 text-start">
              {withHigh ? t('dialog.low') : t('dialog.value')}
            </th>
            {withHigh && (
              <>
                <th scope="col" className="px-1 py-1">
                  <span className="visually-hidden">{t('dialog.and')}</span>
                </th>
                <th scope="col" id={highHeaderId} className="px-2 py-1 text-start">
                  {t('dialog.high')}
                </th>
              </>
            )}
            <th scope="col" className="w-10 px-1 py-1">
              <span className="visually-hidden">{t('dialog.actions')}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {shown.map((row, index) => {
            const rowHeaderId = `${id}-${tab}-${String(index)}-row`;
            const isTrailing = index === rows.length;

            return (
              <tr key={row.key} className="selection-entry-row">
                <th scope="row" id={rowHeaderId} className="px-2 py-1 text-end font-normal text-ink-muted">
                  {index + 1}
                </th>
                <td className="px-2 py-1">
                  {withHigh ? (
                    <span className="text-ink-muted">{t('dialog.between')}</span>
                  ) : (
                    <select
                      className="field-input w-40"
                      aria-labelledby={`${operatorHeaderId} ${rowHeaderId}`}
                      value={row.option}
                      onChange={(event) => {
                        update(index, { option: event.target.value as SingleOption });
                      }}
                    >
                      {singleOptions.map((option) => (
                        <option key={option} value={option}>
                          {t(`operators.${option}`)}
                        </option>
                      ))}
                    </select>
                  )}
                </td>
                <td className="px-2 py-1">
                  <SelectionInput
                    field={field}
                    id={`${id}-${tab}-${String(index)}-low`}
                    value={row.low}
                    check={false}
                    aria-labelledby={`${lowHeaderId} ${rowHeaderId}`}
                    onChange={(value) => {
                      update(index, { low: value });
                    }}
                    onMessage={onMessage}
                  />
                </td>
                {withHigh && (
                  <>
                    <td className="px-1 py-1 text-ink-muted">{t('dialog.and')}</td>
                    <td className="px-2 py-1">
                      <SelectionInput
                        field={field}
                        id={`${id}-${tab}-${String(index)}-high`}
                        value={row.high}
                        check={false}
                        aria-labelledby={`${highHeaderId} ${rowHeaderId}`}
                        onChange={(value) => {
                          update(index, { high: value });
                        }}
                        onMessage={onMessage}
                      />
                    </td>
                  </>
                )}
                <td className="px-1 py-1 text-center">
                  {!isTrailing && (
                    <button
                      type="button"
                      className="inline-flex min-h-7 min-w-7 items-center justify-center rounded-sm text-ink-muted hover:bg-error-soft hover:text-error pointer-coarse:min-h-11 pointer-coarse:min-w-11"
                      aria-label={t('dialog.deleteRow', { row: index + 1 })}
                      title={t('dialog.deleteRow', { row: index + 1 })}
                      onClick={() => {
                        onRowsChange(rows.filter((_, position) => position !== index));
                      }}
                    >
                      <Icon name="deleteRow" />
                    </button>
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
