import type { RowData } from '@tanstack/react-table';
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '../Button';
import { DataGrid } from '../grid/DataGrid';
import { GridDialog } from '../grid/GridDialog';
import type { GridMessage, GridSource } from '../grid/gridTypes';
import { matchesFilter } from '../grid/gridValues';
import type { SearchHelp } from './searchHelpTypes';

interface SearchHelpDialogProps<TData> {
  open: boolean;
  searchHelp: SearchHelp<TData>;
  // Restricción inicial del primer criterio (lo que se escribió en el campo, D2 de E2.17).
  initialCriterion: string;
  // Valor vigente del campo: su fila recibe el foco al abrir.
  currentValue: string | undefined;
  onSelect: (row: TData) => void;
  onCancel: () => void;
  onMessage: (message: GridMessage) => void;
}

// Popup de la ayuda de búsqueda (F4): restricciones arriba, que filtran en vivo, y la lista
// de resultados abajo sobre la grilla ALV (D1 de E2.17), en modo local o, con fuente de
// servidor, paginada en el servidor. Enter o doble clic en una fila toman el valor; Esc cancela.
export function SearchHelpDialog<TData extends RowData>(props: SearchHelpDialogProps<TData>) {
  const { t } = useTranslation('searchHelp');

  return (
    <GridDialog
      open={props.open}
      title={props.searchHelp.title}
      size="wide"
      initialFocus={(dialog) => focusTarget(dialog, props.currentValue)}
      onCancel={props.onCancel}
    >
      <SearchHelpBody {...props} />
      <div className="flex flex-wrap justify-end gap-2 border-t border-border px-4 py-2">
        <Button onClick={props.onCancel}>{t('cancel')}</Button>
      </div>
    </GridDialog>
  );
}

// Al abrir, el foco va a la primera restricción; si el campo ya tiene un valor válido, a su
// fila, como el cursor de SAP sobre el valor vigente.
function focusTarget(dialog: HTMLDialogElement, currentValue: string | undefined): HTMLElement | null | undefined {
  if (currentValue === undefined) {
    return undefined;
  }

  const row = [...dialog.querySelectorAll<HTMLElement>('tr[data-row-id]')].find((element) => element.dataset['rowId'] === currentValue);

  return row?.querySelector<HTMLElement>('[data-cell]');
}

// El cuerpo existe solo con el diálogo abierto (GridDialog no renderiza sus hijos cerrado):
// cada apertura parte de las restricciones iniciales sin efectos que las reinicien.
function SearchHelpBody<TData extends RowData>({
  searchHelp,
  initialCriterion,
  onSelect,
  onMessage,
}: Omit<SearchHelpDialogProps<TData>, 'currentValue'>) {
  const { t } = useTranslation('searchHelp');
  const id = useId();
  const bodyRef = useRef<HTMLDivElement>(null);
  const criteriaColumns = useMemo(
    () => searchHelp.criteria.map((columnId) => searchHelp.columns.find((column) => column.id === columnId)).filter((column) => column !== undefined),
    [searchHelp],
  );
  const [criteria, setCriteria] = useState<Readonly<Record<string, string>>>(() => {
    const first = criteriaColumns[0];

    return first === undefined || initialCriterion === '' ? {} : { [first.id]: initialCriterion };
  });

  const local = searchHelp.source.kind === 'local' ? searchHelp.source : undefined;
  const server = searchHelp.source.kind === 'server' ? searchHelp.source : undefined;

  const rows = useMemo(
    () =>
      local?.rows.filter((row) =>
        criteriaColumns.every((column) => matchesFilter(column, column.value(row), { operator: 'contains', values: [criteria[column.id] ?? ''] })),
      ) ?? [],
    [local, criteriaColumns, criteria],
  );

  // En modo servidor las restricciones se aplican con una pausa breve al escribir, para no
  // pedir una página por tecla; Enter las aplica en el momento.
  const [appliedCriteria, setAppliedCriteria] = useState(criteria);

  useEffect(() => {
    if (server === undefined) {
      return undefined;
    }

    const timer = window.setTimeout(() => {
      setAppliedCriteria(criteria);
    }, searchDelayMs);

    return () => {
      window.clearTimeout(timer);
    };
  }, [server, criteria]);

  const gridSource = useMemo((): GridSource<TData> => {
    if (server === undefined) {
      return { kind: 'local', rows };
    }

    const filters = server.filtersOf(nonEmpty(appliedCriteria));

    return {
      kind: 'server',
      queryKey: [...server.queryKey, 'searchHelp', filters],
      fetchPage: (params, signal) => server.fetchPage({ ...params, filters: { ...params.filters, ...filters } }, signal),
    };
  }, [server, rows, appliedCriteria]);

  const takeSingleResult = async (): Promise<boolean> => {
    if (server === undefined) {
      const [only, ...rest] = rows;

      if (only !== undefined && rest.length === 0) {
        onSelect(only);

        return true;
      }

      return false;
    }

    setAppliedCriteria(criteria);

    try {
      const page = await server.fetchPage(
        { page: 1, perPage: 2, filters: server.filtersOf(nonEmpty(criteria)) },
        new AbortController().signal,
      );
      const [only] = page.items;

      if (only !== undefined && page.total === 1) {
        onSelect(only);

        return true;
      }
    } catch {
      // La grilla muestra el error de la consulta.
    }

    return false;
  };

  const onCriterionKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key !== 'Enter') {
      return;
    }

    event.preventDefault();

    // Con un único resultado, Enter lo toma; si hay más, el foco baja a la lista.
    void takeSingleResult().then((taken) => {
      if (!taken) {
        bodyRef.current?.querySelector<HTMLElement>('tr[data-row-id] [data-cell]')?.focus();
      }
    });
  };

  return (
    <div ref={bodyRef} className="grid max-h-[70dvh] gap-3 overflow-auto px-4 py-3">
      <fieldset className="grid gap-1.5">
        <legend className="mb-1 text-xs font-semibold text-ink-muted">{t('criteria')}</legend>
        {criteriaColumns.map((column) => {
          const inputId = `${id}-${column.id}`;

          return (
            <div key={column.id} className="grid grid-cols-[minmax(6rem,10rem)_1fr] items-center gap-x-3">
              <label htmlFor={inputId} className="text-ink-muted">
                {column.header}
              </label>
              <input
                id={inputId}
                className="field-input max-w-72"
                autoComplete="off"
                value={criteria[column.id] ?? ''}
                onChange={(event) => {
                  setCriteria((previous) => ({ ...previous, [column.id]: event.target.value }));
                }}
                onKeyDown={onCriterionKeyDown}
              />
            </div>
          );
        })}
      </fieldset>
      {/* La grilla ya muestra la cantidad de filas: el conteo se anuncia, no se repite a la vista. */}
      {local !== undefined && (
        <p className="visually-hidden" aria-live="polite">
          {t('hits', { count: rows.length })}
        </p>
      )}
      <DataGrid
        label={t('results', { title: searchHelp.title })}
        columns={searchHelp.columns}
        source={gridSource}
        rowKey={searchHelp.valueOf}
        exportName={searchHelp.exportName}
        onMessage={onMessage}
        onRowActivate={onSelect}
      />
    </div>
  );
}

const searchDelayMs = 300;

function nonEmpty(criteria: Readonly<Record<string, string>>): Record<string, string> {
  return Object.fromEntries(
    Object.entries(criteria)
      .map(([id, text]) => [id, text.trim()] as const)
      .filter(([, text]) => text !== ''),
  );
}
