import {
  columnFilteringFeature,
  createFilteredRowModel,
  createSortedRowModel,
  rowSortingFeature,
  tableFeatures,
  useTable,
  type ColumnDef,
  type RowData,
} from '@tanstack/react-table';
import { useEffect, useMemo, useRef, useState, type KeyboardEvent, type MouseEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@/shared/api/ApiError';
import type { ListParams } from '@/shared/api/listing';
import { problemMessage } from '@/shared/errors/problemMessage';
import { formatNumber } from '@/shared/format/format';
import { Button } from '../Button';
import { ContextMenu } from '../ContextMenu';
import { keepsNativeContextMenu, type ContextMenuItem } from '../contextMenuItems';
import { Icon } from '../icons';
import { useContextMenu } from '../useContextMenu';
import { csvFileName, downloadCsv, toCsv } from './csv';
import { GridFilterDialog, type GridFilterTarget } from './GridFilterDialog';
import { GridLayoutDialog } from './GridLayoutDialog';
import { GridToolbar, type GridToolbarAction } from './GridToolbar';
import type {
  GridColumn,
  GridFilter,
  GridFilterOperator,
  GridLayout,
  GridMessage,
  GridSort,
  GridSource,
  GridValue,
} from './gridTypes';
import { buildDisplayRows, compareGridValues, formatGridValue, matchesFilter, type GridDisplayRow } from './gridValues';
import { fetchAllPages, maxExportRows, useGridData } from './useGridData';
import { cellAttribute, useGridNavigation } from './useGridNavigation';

// Grilla tipo ALV (E2.00, ADR-016) sobre TanStack Table 9.
//
// Dos modos (D2): en modo servidor, orden, filtro y página se traducen a los parámetros de
// ListRequest (E2.10) y solo se ofrecen para los campos que el listado declara; en modo local,
// TanStack Table ordena y filtra en el cliente, y hay totales y subtotales (D3), como la ALV
// sobre la tabla interna completa. El estado de la tabla (orden, filtros, disposición) vive en
// este componente: las pantallas declaran columnas y fuente de datos.

const features = tableFeatures({
  rowSortingFeature,
  columnFilteringFeature,
  sortedRowModel: createSortedRowModel(),
  filteredRowModel: createFilteredRowModel(),
});

type Features = typeof features;

export const defaultPageSize = 50;

export interface DataGridProps<TData extends RowData> {
  // Nombre accesible de la grilla.
  label: string;
  columns: readonly GridColumn<TData>[];
  source: GridSource<TData>;
  rowKey: (row: TData) => string | number;
  // Base del nombre del archivo CSV (por ejemplo, "productos").
  exportName: string;
  // Mensajes para la barra de estado de la transacción (S, W, E, I).
  onMessage: (message: GridMessage) => void;
  // Doble clic o Enter sobre una fila: abrir la visualización, tomar el valor de un F4.
  onRowActivate?: (row: TData) => void;
  selection?: 'none' | 'multiple';
  onSelectionChange?: (rows: TData[]) => void;
  initialSort?: GridSort;
  pageSize?: number;
}

function filterOperator<TData>(column: GridColumn<TData>, server: boolean): GridFilterOperator | undefined {
  if (column.filter === false) {
    return undefined;
  }

  if (server || column.filter !== undefined) {
    return column.filter;
  }

  if (column.options !== undefined) {
    return 'in';
  }

  return (column.type ?? 'text') === 'text' ? 'contains' : 'equals';
}

function ariaSortOf(sort: GridSort | undefined, columnId: string): 'ascending' | 'descending' | undefined {
  if (sort?.columnId !== columnId) {
    return undefined;
  }

  return sort.desc ? 'descending' : 'ascending';
}

function canSortColumn<TData>(column: GridColumn<TData>, server: boolean): boolean {
  return server ? column.sortable === true : column.sortable !== false;
}

function initialLayoutOf<TData>(columns: readonly GridColumn<TData>[]): GridLayout {
  return {
    order: columns.map((column) => column.id),
    hidden: columns.filter((column) => column.hidden === true).map((column) => column.id),
  };
}

// La disposición guarda ids: si cambian las columnas declaradas, las desconocidas se descartan
// y las nuevas se agregan al final, visibles según su declaración.
function reconcileLayout<TData>(layout: GridLayout, columns: readonly GridColumn<TData>[]): GridLayout {
  const known = new Set(columns.map((column) => column.id));
  const order = layout.order.filter((id) => known.has(id));
  const added = columns.filter((column) => !order.includes(column.id));

  return {
    order: [...order, ...added.map((column) => column.id)],
    hidden: [
      ...layout.hidden.filter((id) => known.has(id)),
      ...added.filter((column) => column.hidden === true).map((column) => column.id),
    ],
  };
}

export function DataGrid<TData extends RowData>({
  label,
  columns,
  source,
  rowKey,
  exportName,
  onMessage,
  onRowActivate,
  selection = 'none',
  onSelectionChange,
  initialSort,
  pageSize = defaultPageSize,
}: DataGridProps<TData>) {
  const { t } = useTranslation('grid');
  const { t: tCommon } = useTranslation('common');
  const server = source.kind === 'server';
  const containerRef = useRef<HTMLDivElement>(null);
  const menu = useContextMenu<string>();

  const [sort, setSort] = useState<GridSort | undefined>(initialSort);
  const [filters, setFilters] = useState<Readonly<Record<string, GridFilter>>>({});
  const [storedLayout, setLayout] = useState<GridLayout>(() => initialLayoutOf(columns));
  const [totaled, setTotaled] = useState<readonly string[]>([]);
  const [subtotal, setSubtotal] = useState<string>();
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<ReadonlySet<string>>(new Set());
  const [filterTarget, setFilterTarget] = useState<GridFilterTarget<TData> | null>(null);
  const [layoutOpen, setLayoutOpen] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [announcement, setAnnouncement] = useState('');
  // Una exportación en curso se cancela si se cierra la transacción: no sigue pidiendo páginas.
  const exportAbort = useRef<AbortController>(undefined);

  useEffect(
    () => () => {
      exportAbort.current?.abort();
    },
    [],
  );

  const byId = useMemo(() => new Map(columns.map((column) => [column.id, column])), [columns]);
  const layout = useMemo(() => reconcileLayout(storedLayout, columns), [storedLayout, columns]);
  const visible = useMemo(
    () =>
      layout.order
        .filter((id) => !layout.hidden.includes(id))
        .map((id) => byId.get(id))
        .filter((column) => column !== undefined),
    [layout, byId],
  );

  // Totales y subtotales solo en modo local (D3): sobre una página serían engañosos.
  const totaledColumns = useMemo(
    () => (server ? [] : visible.filter((column) => column.summable === true && totaled.includes(column.id))),
    [server, visible, totaled],
  );
  const subtotalColumn = server || totaledColumns.length === 0 || subtotal === undefined ? undefined : byId.get(subtotal);

  const params: ListParams = useMemo(() => {
    const serverFilters: Record<string, string | readonly string[]> = {};

    for (const [id, filter] of Object.entries(filters)) {
      const column = byId.get(id);

      if (column !== undefined) {
        const field = column.field ?? column.id;

        serverFilters[field] = filter.operator === 'in' ? filter.values : (filter.values[0] ?? '');
      }
    }

    const sortColumn = sort === undefined ? undefined : byId.get(sort.columnId);

    return {
      page,
      perPage: pageSize,
      sort: sortColumn === undefined || sort === undefined ? undefined : { field: sortColumn.field ?? sortColumn.id, desc: sort.desc },
      filters: serverFilters,
    };
  }, [filters, sort, page, pageSize, byId]);

  const data = useGridData(source, params);

  const latestMessage = useRef(onMessage);
  useEffect(() => {
    latestMessage.current = onMessage;
  });

  const loadError = data.error;
  useEffect(() => {
    if (loadError !== null && loadError !== undefined) {
      latestMessage.current(
        loadError instanceof ApiError
          ? { type: 'E', text: problemMessage(loadError), id: loadError.code }
          : { type: 'E', text: t('messages.loadFailed') },
      );
    }
  }, [loadError, t]);

  const tableColumns = useMemo(
    () =>
      columns.map(
        (column): ColumnDef<Features, TData> => ({
          id: column.id,
          accessorFn: (row) => column.value(row),
          sortFn: (rowA, rowB, columnId) =>
            compareGridValues(column.type, rowA.getValue<GridValue>(columnId), rowB.getValue<GridValue>(columnId)),
          filterFn: (row, columnId, filter: GridFilter) => matchesFilter(column, row.getValue<GridValue>(columnId), filter),
        }),
      ),
    [columns],
  );

  // El subtotal agrupa: las filas se ordenan primero por esa columna, como en la ALV.
  const sorting = useMemo(() => {
    if (server || subtotalColumn === undefined) {
      return sort === undefined ? [] : [{ id: sort.columnId, desc: sort.desc }];
    }

    const groupDesc = sort?.columnId === subtotalColumn.id && sort.desc;
    const rest = sort === undefined || sort.columnId === subtotalColumn.id ? [] : [{ id: sort.columnId, desc: sort.desc }];

    return [{ id: subtotalColumn.id, desc: groupDesc }, ...rest];
  }, [server, sort, subtotalColumn]);

  const columnFilters = useMemo(() => Object.entries(filters).map(([id, value]) => ({ id, value })), [filters]);

  const table = useTable(
    {
      features,
      columns: tableColumns,
      data: data.rows,
      getRowId: (row) => String(rowKey(row)),
      state: { sorting, columnFilters },
      manualSorting: server,
      manualFiltering: server,
    },
    () => null,
  );

  const tableRows = table.getRowModel().rows;
  const displayRows = useMemo(
    () =>
      buildDisplayRows({
        rows: tableRows.map((row) => ({ id: row.id, row: row.original })),
        totaled: totaledColumns,
        subtotal: subtotalColumn,
      }),
    [tableRows, totaledColumns, subtotalColumn],
  );

  const multiple = selection === 'multiple';
  const offset = multiple ? 1 : 0;
  const colCount = visible.length + offset;
  const rowCount = displayRows.length + 1;
  const navigation = useGridNavigation({ rowCount, colCount, containerRef });
  const currentColumn = navigation.active.col >= offset ? visible[navigation.active.col - offset] : undefined;

  const dataRowIds = useMemo(
    () => displayRows.filter((row) => row.kind === 'data').map((row) => row.id),
    [displayRows],
  );

  // En modo servidor la selección es de la página visible: cambiar de página, orden o filtro
  // la descarta, como la ALV al refrescar la lista.
  const visibleSelected = useMemo(() => dataRowIds.filter((id) => selected.has(id)), [dataRowIds, selected]);
  const latestSelection = useRef(onSelectionChange);
  useEffect(() => {
    latestSelection.current = onSelectionChange;
  });
  const rowsById = useMemo(() => new Map(tableRows.map((row) => [row.id, row.original])), [tableRows]);
  useEffect(() => {
    latestSelection.current?.(visibleSelected.map((id) => rowsById.get(id)).filter((row) => row !== undefined));
  }, [visibleSelected, rowsById]);

  const goToPage = (next: number) => {
    setPage(next);
    setSelected(new Set());
  };

  const resetPaging = () => {
    goToPage(1);
  };

  const applySort = (columnId: string, desc: boolean) => {
    const column = byId.get(columnId);

    if (column === undefined || !canSortColumn(column, server)) {
      return;
    }

    setSort({ columnId, desc });
    resetPaging();
    setAnnouncement(
      t('announce.sorted', {
        column: column.header,
        direction: t(desc ? 'announce.descending' : 'announce.ascending'),
      }),
    );
  };

  const toggleSort = (columnId: string) => {
    // Primera activación ascendente; repetir alterna; otra columna empieza ascendente.
    applySort(columnId, sort?.columnId === columnId ? !sort.desc : false);
  };

  const openFilter = (columnId: string) => {
    const column = byId.get(columnId);
    const operator = column === undefined ? undefined : filterOperator(column, server);

    if (column !== undefined && operator !== undefined) {
      setFilterTarget({ column, operator, current: filters[columnId] });
    }
  };

  const setFilter = (columnId: string, filter: GridFilter | undefined) => {
    setFilters((previous) => {
      const rest = Object.fromEntries(Object.entries(previous).filter(([id]) => id !== columnId));

      return filter === undefined ? rest : { ...rest, [columnId]: filter };
    });
    resetPaging();
  };

  const toggleTotal = (columnId: string) => {
    setTotaled((previous) => (previous.includes(columnId) ? previous.filter((id) => id !== columnId) : [...previous, columnId]));
  };

  const toggleSubtotal = (columnId: string) => {
    setSubtotal((previous) => (previous === columnId ? undefined : columnId));
  };

  const hideColumn = (columnId: string) => {
    if (visible.length > 1) {
      setLayout({ ...layout, hidden: [...layout.hidden, columnId] });
    }
  };

  const exportRows = async () => {
    if (exporting) {
      return;
    }

    const controller = new AbortController();

    exportAbort.current = controller;
    setExporting(true);

    try {
      let rows: TData[];
      let total: number;

      if (source.kind === 'server') {
        const result = await fetchAllPages(
          source.fetchPage,
          { sort: params.sort, filters: params.filters },
          controller.signal,
        );

        rows = result.rows;
        total = result.total;
      } else {
        rows = tableRows.map((row) => row.original);
        total = rows.length;
      }

      downloadCsv(csvFileName(exportName), toCsv(visible, rows));
      onMessage(
        rows.length < total
          ? { type: 'W', text: t('messages.exportTruncated', { count: maxExportRows, total }) }
          : { type: 'S', text: t('messages.exported', { count: rows.length }) },
      );
    } catch (error) {
      if (controller.signal.aborted) {
        return;
      }

      onMessage(
        error instanceof ApiError
          ? { type: 'E', text: problemMessage(error), id: error.code }
          : { type: 'E', text: t('messages.exportFailed') },
      );
    } finally {
      if (!controller.signal.aborted) {
        setExporting(false);
      }
    }
  };

  const columnLabel = (action: string) =>
    currentColumn === undefined ? action : t('toolbar.currentColumn', { action, column: currentColumn.header });
  const currentSortable = currentColumn !== undefined && canSortColumn(currentColumn, server);
  const currentFilterable = currentColumn !== undefined && filterOperator(currentColumn, server) !== undefined;
  const currentSummable = currentColumn?.summable === true;

  const toolbarGroups: GridToolbarAction[][] = [
    [
      {
        id: 'sortAscending',
        icon: 'sortAscending',
        label: columnLabel(t('toolbar.sortAscending')),
        disabled: !currentSortable,
        onClick: () => {
          if (currentColumn !== undefined) {
            applySort(currentColumn.id, false);
          }
        },
      },
      {
        id: 'sortDescending',
        icon: 'sortDescending',
        label: columnLabel(t('toolbar.sortDescending')),
        disabled: !currentSortable,
        onClick: () => {
          if (currentColumn !== undefined) {
            applySort(currentColumn.id, true);
          }
        },
      },
    ],
    [
      {
        id: 'filter',
        icon: 'filter',
        label: columnLabel(t('toolbar.filter')),
        disabled: !currentFilterable,
        onClick: () => {
          if (currentColumn !== undefined) {
            openFilter(currentColumn.id);
          }
        },
      },
      {
        id: 'clearFilters',
        icon: 'filterOff',
        label: t('toolbar.clearFilters'),
        disabled: Object.keys(filters).length === 0,
        onClick: () => {
          setFilters({});
          resetPaging();
        },
      },
    ],
    server
      ? []
      : [
          {
            id: 'total',
            icon: 'sum',
            label: columnLabel(t('toolbar.total')),
            disabled: !currentSummable,
            pressed: currentColumn !== undefined && currentSummable ? totaled.includes(currentColumn.id) : undefined,
            onClick: () => {
              if (currentColumn !== undefined) {
                toggleTotal(currentColumn.id);
              }
            },
          },
          {
            id: 'subtotal',
            icon: 'subtotal',
            label: columnLabel(t('toolbar.subtotal')),
            disabled: currentColumn === undefined || totaledColumns.length === 0,
            pressed: currentColumn !== undefined && totaledColumns.length > 0 ? subtotal === currentColumn.id : undefined,
            onClick: () => {
              if (currentColumn !== undefined) {
                toggleSubtotal(currentColumn.id);
              }
            },
          },
        ],
    [
      {
        id: 'columns',
        icon: 'columns',
        label: t('toolbar.columns'),
        onClick: () => {
          setLayoutOpen(true);
        },
      },
      {
        id: 'export',
        icon: 'export',
        label: t('toolbar.export'),
        disabled: exporting || data.total === 0,
        onClick: () => {
          void exportRows();
        },
      },
    ],
  ];

  // Menú contextual de una columna (ADR-016): ordenar, filtrar, totales y disposición. Cada
  // acción existe también en la barra de la grilla o en la cabecera.
  const columnMenuItems = (column: GridColumn<TData>): ContextMenuItem[] => {
    const sortable = canSortColumn(column, server);
    const items: ContextMenuItem[] = [
      {
        kind: 'action',
        id: 'sortAscending',
        label: t('toolbar.sortAscending'),
        icon: 'sortAscending',
        disabled: !sortable,
        onSelect: () => {
          applySort(column.id, false);
        },
      },
      {
        kind: 'action',
        id: 'sortDescending',
        label: t('toolbar.sortDescending'),
        icon: 'sortDescending',
        disabled: !sortable,
        onSelect: () => {
          applySort(column.id, true);
        },
      },
      { kind: 'separator', id: 'separator-sort' },
      {
        kind: 'action',
        id: 'filter',
        label: t('toolbar.filter'),
        icon: 'filter',
        disabled: filterOperator(column, server) === undefined,
        onSelect: () => {
          openFilter(column.id);
        },
      },
    ];

    if (filters[column.id] !== undefined) {
      items.push({
        kind: 'action',
        id: 'removeFilter',
        label: t('menu.removeFilter'),
        icon: 'filterOff',
        onSelect: () => {
          setFilter(column.id, undefined);
        },
      });
    }

    if (!server && column.summable === true) {
      items.push(
        { kind: 'separator', id: 'separator-total' },
        {
          kind: 'checkbox',
          id: 'total',
          label: t('toolbar.total'),
          checked: totaled.includes(column.id),
          onChange: () => {
            toggleTotal(column.id);
          },
        },
      );
    }

    if (!server && totaledColumns.length > 0) {
      items.push({
        kind: 'checkbox',
        id: 'subtotal',
        label: t('toolbar.subtotal'),
        checked: subtotal === column.id,
        onChange: () => {
          toggleSubtotal(column.id);
        },
      });
    }

    items.push(
      { kind: 'separator', id: 'separator-layout' },
      {
        kind: 'action',
        id: 'hideColumn',
        label: t('menu.hideColumn'),
        disabled: visible.length <= 1,
        onSelect: () => {
          hideColumn(column.id);
        },
      },
      {
        kind: 'action',
        id: 'columns',
        label: t('toolbar.columns'),
        icon: 'columns',
        onSelect: () => {
          setLayoutOpen(true);
        },
      },
    );

    return items;
  };

  const menuColumn = menu.state === null ? undefined : byId.get(menu.state.target);
  const menuItems = menuColumn === undefined ? [] : columnMenuItems(menuColumn);

  const onContextMenu = (event: MouseEvent<HTMLElement>) => {
    const cell = event.target instanceof Element ? event.target.closest<HTMLElement>('[data-column]') : null;
    const columnId = cell?.dataset['column'];

    if (columnId === undefined || keepsNativeContextMenu(event)) {
      return;
    }

    const origin = cell?.querySelector<HTMLElement>('[data-cell]') ?? cell ?? undefined;

    menu.open(event, columnId, origin);
  };

  const toggleRow = (id: string) => {
    setSelected((previous) => {
      const next = new Set(previous);

      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }

      return next;
    });
  };

  const allSelected = dataRowIds.length > 0 && visibleSelected.length === dataRowIds.length;

  const onBodyKeyDown = (event: KeyboardEvent<HTMLTableRowElement>, row: GridDisplayRow<TData>) => {
    if (row.kind !== 'data' || event.target instanceof HTMLInputElement) {
      return;
    }

    if (event.key === 'Enter' && onRowActivate !== undefined) {
      event.preventDefault();
      onRowActivate(row.row);
    } else if (event.key === ' ' && multiple) {
      event.preventDefault();
      toggleRow(row.id);
    }
  };

  const tabIndexOf = (row: number, col: number) =>
    navigation.active.row === row && navigation.active.col === col ? 0 : -1;

  const alignOf = (column: GridColumn<TData>) => ((column.type ?? 'text') === 'text' ? 'text-start' : 'text-end');

  const renderCell = (column: GridColumn<TData>, row: GridDisplayRow<TData>, rowIndex: number, colIndex: number): ReactNode => {
    const common = {
      'data-cell': cellAttribute(rowIndex, colIndex),
      'data-column': column.id,
      tabIndex: tabIndexOf(rowIndex, colIndex),
      className: `border-b border-e border-border px-2 py-1 whitespace-nowrap ${alignOf(column)}`,
    };

    if (row.kind === 'data') {
      return (
        <td key={column.id} role="gridcell" {...common}>
          {column.cell?.(row.row) ?? formatGridValue(column, column.value(row.row), row.row)}
        </td>
      );
    }

    const isFirst = colIndex === offset;
    const sum = row.totals.sums[column.id];
    let content = '';

    if (sum !== undefined) {
      content = formatNumber(sum);
    } else if (isFirst) {
      content =
        row.kind === 'total'
          ? t('rows.total')
          : t('rows.subtotal', { value: subtotalColumn === undefined ? '' : formatGridValue(subtotalColumn, row.group, row.groupRow) });
    }

    return (
      <td key={column.id} role="gridcell" {...common}>
        {content}
      </td>
    );
  };

  const statusRow = (text: string, action?: ReactNode) => (
    <tr>
      <td role="gridcell" colSpan={colCount} className="px-2 py-3 text-ink-muted">
        <span>{text}</span>
        {action}
      </td>
    </tr>
  );

  const from = data.total === 0 ? 0 : (page - 1) * pageSize + 1;
  const to = Math.min(page * pageSize, data.total);

  return (
    <div className="flex min-h-0 flex-col gap-1">
      <GridToolbar groups={toolbarGroups} />

      {Object.keys(filters).length > 0 && (
        <ul aria-label={t('filters.label')} className="flex flex-wrap gap-1">
          {Object.entries(filters).map(([id, filter]) => {
            const column = byId.get(id);

            if (column === undefined) {
              return null;
            }

            const optionLabel = (value: string) => column.options?.find((option) => option.value === value)?.label ?? value;
            const text =
              filter.operator === 'in'
                ? t('filters.in', { column: column.header, values: filter.values.map(optionLabel).join(', ') })
                : t(`filters.${filter.operator}`, { column: column.header, value: optionLabel(filter.values[0] ?? '') });

            return (
              <li key={id} className="inline-flex items-center gap-1 rounded-sm border border-border bg-info-soft px-2 py-0.5">
                <span>{text}</span>
                <button
                  type="button"
                  className="inline-flex size-5 items-center justify-center rounded-sm hover:bg-hover pointer-coarse:size-11"
                  aria-label={t('filters.remove', { filter: text })}
                  onClick={() => {
                    setFilter(id, undefined);
                  }}
                >
                  <Icon name="error" className="size-3.5" />
                </button>
              </li>
            );
          })}
        </ul>
      )}

      <div ref={containerRef} className="min-h-0 overflow-auto rounded-sm border border-border bg-surface">
        <table
          role="grid"
          aria-label={label}
          aria-rowcount={server ? data.total + 1 : rowCount}
          aria-colcount={colCount}
          aria-multiselectable={multiple ? true : undefined}
          aria-busy={data.isFetching || exporting}
          className="w-full border-separate border-spacing-0"
          onFocus={navigation.onFocus}
          onKeyDown={navigation.onKeyDown}
          onContextMenu={onContextMenu}
        >
          <thead className="sticky top-0 z-10 bg-surface-alt">
            <tr aria-rowindex={1}>
              {multiple && (
                <th scope="col" className="w-8 border-b border-e border-border-strong/40 px-2 py-1">
                  <input
                    type="checkbox"
                    aria-label={t('selection.all')}
                    data-cell={cellAttribute(0, 0)}
                    tabIndex={tabIndexOf(0, 0)}
                    checked={allSelected}
                    // aria-disabled y no disabled: sin filas sigue siendo la celda activa y la
                    // grilla no pierde su parada de Tab.
                    aria-disabled={dataRowIds.length === 0}
                    onChange={() => {
                      if (dataRowIds.length > 0) {
                        setSelected(allSelected ? new Set() : new Set(dataRowIds));
                      }
                    }}
                  />
                </th>
              )}
              {visible.map((column, index) => {
                const colIndex = index + offset;
                const sorted = ariaSortOf(sort, column.id);
                const filtered = filters[column.id] !== undefined;
                const headerClass = `border-b border-e border-border-strong/40 font-semibold whitespace-nowrap ${alignOf(column)}`;
                const marker = (
                  <>
                    {filtered && <Icon name="filter" className="size-3.5 text-accent" />}
                    {sorted !== undefined && (
                      <Icon name={sorted === 'ascending' ? 'sortAscending' : 'sortDescending'} className="size-3.5 text-accent" />
                    )}
                  </>
                );

                return canSortColumn(column, server) ? (
                  <th key={column.id} scope="col" aria-sort={sorted} data-column={column.id} className={headerClass}>
                    <button
                      type="button"
                      data-cell={cellAttribute(0, colIndex)}
                      tabIndex={tabIndexOf(0, colIndex)}
                      className={`inline-flex min-h-7 w-full items-center gap-1 px-2 hover:bg-hover pointer-coarse:min-h-11 ${(column.type ?? 'text') === 'text' ? 'justify-start' : 'justify-end'}`}
                      onClick={() => {
                        toggleSort(column.id);
                      }}
                    >
                      {column.header}
                      {marker}
                    </button>
                  </th>
                ) : (
                  <th
                    key={column.id}
                    scope="col"
                    data-column={column.id}
                    data-cell={cellAttribute(0, colIndex)}
                    tabIndex={tabIndexOf(0, colIndex)}
                    className={`${headerClass} px-2 py-1`}
                  >
                    <span className="inline-flex items-center gap-1">
                      {column.header}
                      {marker}
                    </span>
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {displayRows.length === 0 &&
              (data.isLoading
                ? statusRow(t('rows.loading'))
                : data.error !== null && data.error !== undefined
                  ? statusRow(
                      t('rows.error'),
                      <Button variant="ghost" className="ms-2" onClick={data.refetch}>
                        {tCommon('actions.retry')}
                      </Button>,
                    )
                  : statusRow(t('rows.empty')))}
            {displayRows.map((row, index) => {
              const rowIndex = index + 1;
              const isData = row.kind === 'data';
              const isSelected = isData && selected.has(row.id);

              return (
                <tr
                  key={row.id}
                  data-row-id={isData ? row.id : undefined}
                  aria-rowindex={server ? (page - 1) * pageSize + rowIndex + 1 : rowIndex + 1}
                  aria-selected={multiple && isData ? isSelected : undefined}
                  className={
                    isData
                      ? `focus-within:bg-selected aria-selected:bg-selected hover:bg-hover ${index % 2 === 1 ? 'bg-surface-alt/60' : ''}`
                      : 'bg-toolbar font-semibold'
                  }
                  onKeyDown={(event) => {
                    onBodyKeyDown(event, row);
                  }}
                  onDoubleClick={() => {
                    if (row.kind === 'data') {
                      onRowActivate?.(row.row);
                    }
                  }}
                >
                  {multiple && (
                    <td role="gridcell" className="border-b border-e border-border px-2 py-1">
                      {isData && (
                        <input
                          type="checkbox"
                          aria-label={t('selection.row')}
                          data-cell={cellAttribute(rowIndex, 0)}
                          tabIndex={tabIndexOf(rowIndex, 0)}
                          checked={isSelected}
                          onChange={() => {
                            toggleRow(row.id);
                          }}
                        />
                      )}
                    </td>
                  )}
                  {visible.map((column, columnIndex) => renderCell(column, row, rowIndex, columnIndex + offset))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2 text-ink-muted">
        <span>
          {server
            ? t('footer.range', { from: formatNumber(from), to: formatNumber(to), total: formatNumber(data.total) })
            : tableRows.length === data.total
              ? t('footer.count', { count: data.total })
              : t('footer.filteredCount', { count: tableRows.length, total: data.total })}
        </span>
        {server && data.lastPage > 1 && (
          <nav aria-label={t('footer.pagination')} className="flex items-center gap-0.5">
            <PageButton icon="first" label={t('footer.first')} disabled={page <= 1} onClick={() => { goToPage(1); }} />
            <PageButton icon="previous" label={t('footer.previous')} disabled={page <= 1} onClick={() => { goToPage(page - 1); }} />
            <span className="px-2">{t('footer.page', { page: formatNumber(page), pages: formatNumber(data.lastPage) })}</span>
            <PageButton icon="chevron" label={t('footer.next')} disabled={page >= data.lastPage} onClick={() => { goToPage(page + 1); }} />
            <PageButton icon="last" label={t('footer.last')} disabled={page >= data.lastPage} onClick={() => { goToPage(data.lastPage); }} />
          </nav>
        )}
      </div>

      {/* Un solo anuncio por orden, fuera de la tabla: aria-live sobre la grilla anunciaría cada fila. */}
      <div role="status" className="visually-hidden">
        {announcement}
      </div>

      <ContextMenu
        label={menuColumn === undefined ? '' : t('menu.label', { column: menuColumn.header })}
        state={menu.state}
        items={menuItems}
        onClose={menu.close}
      />
      <GridFilterDialog
        target={filterTarget}
        onApply={setFilter}
        onClose={() => {
          setFilterTarget(null);
        }}
      />
      <GridLayoutDialog
        open={layoutOpen}
        columns={columns}
        layout={layout}
        initialLayout={initialLayoutOf(columns)}
        onApply={setLayout}
        onClose={() => {
          setLayoutOpen(false);
        }}
      />
    </div>
  );
}

interface PageButtonProps {
  icon: 'first' | 'previous' | 'chevron' | 'last';
  label: string;
  disabled: boolean;
  onClick: () => void;
}

// aria-disabled: el botón conserva el foco al llegar a la primera o la última página.
function PageButton({ icon, label, disabled, onClick }: PageButtonProps) {
  return (
    <button
      type="button"
      className="inline-flex size-7 items-center justify-center rounded-sm text-ink hover:bg-hover aria-disabled:cursor-not-allowed aria-disabled:opacity-40 pointer-coarse:size-11"
      aria-label={label}
      title={label}
      aria-disabled={disabled}
      onClick={() => {
        if (!disabled) {
          onClick();
        }
      }}
    >
      <Icon name={icon} />
    </button>
  );
}
