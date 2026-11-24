import { formatDate, formatDateTime, formatNumber, intlLocale } from '@/shared/format/format';
import type { GridColumn, GridFilter, GridValue } from './gridTypes';

// Lógica pura de la grilla: formato, comparación, filtro y totales. Se prueba sin React.

export function isEmptyValue(value: GridValue): value is null | undefined | '' {
  return value === null || value === undefined || value === '';
}

export function formatGridValue<TData>(column: GridColumn<TData>, value: GridValue, row: TData): string {
  if (column.format !== undefined) {
    return column.format(value, row);
  }

  if (isEmptyValue(value)) {
    return '';
  }

  switch (column.type ?? 'text') {
    case 'number':
      return typeof value === 'number' ? formatNumber(value) : String(value);
    case 'date':
      return typeof value === 'string' ? formatDate(value) : String(value);
    case 'datetime':
      return typeof value === 'string' ? formatDateTime(value) : String(value);
    case 'text':
      return String(value);
  }
}

// Texto con Intl.Collator (numérico y sin distinguir mayúsculas ni acentos): "A10" va después
// de "A9" y "Ñandú" se ordena donde corresponde en español. Números y fechas, como valores.
// Los vacíos van al final en los dos sentidos, como en la ALV.
export function compareGridValues(type: GridColumn<unknown>['type'], a: GridValue, b: GridValue): number {
  const emptyA = isEmptyValue(a);
  const emptyB = isEmptyValue(b);

  if (emptyA || emptyB) {
    return Number(emptyA) - Number(emptyB);
  }

  if (typeof a === 'number' && typeof b === 'number') {
    return a - b;
  }

  if (typeof a === 'boolean' && typeof b === 'boolean') {
    return Number(a) - Number(b);
  }

  if (type === 'date' || type === 'datetime') {
    return Date.parse(String(a)) - Date.parse(String(b));
  }

  return new Intl.Collator(intlLocale(), { numeric: true, sensitivity: 'base' }).compare(String(a), String(b));
}

function normalize(text: string): string {
  return text.toLocaleLowerCase(intlLocale()).normalize('NFD').replace(/\p{Diacritic}/gu, '');
}

// Valor comparable con lo que escribe el usuario: los booleanos como 1 y 0, igual que los
// acepta la regla boolean de Laravel; las fechas por su parte de día (AAAA-MM-DD).
export function filterKey(type: GridColumn<unknown>['type'], value: GridValue): string {
  if (typeof value === 'boolean') {
    return value ? '1' : '0';
  }

  if (isEmptyValue(value)) {
    return '';
  }

  if (type === 'date' || type === 'datetime') {
    return String(value).slice(0, 10);
  }

  return String(value);
}

// Mismo criterio que el backend: contains sin distinguir mayúsculas (ILIKE), equals exacto
// salvo mayúsculas en texto, in contra la lista.
export function matchesFilter<TData>(column: GridColumn<TData>, value: GridValue, filter: GridFilter): boolean {
  const key = filterKey(column.type, value);

  switch (filter.operator) {
    case 'contains': {
      const needle = normalize(filter.values[0] ?? '');

      return needle === '' || normalize(key).includes(needle);
    }
    case 'equals': {
      const expected = filter.values[0] ?? '';

      return (column.type ?? 'text') === 'text' ? normalize(key) === normalize(expected) : key === expected;
    }
    case 'in':
      return filter.values.includes(key);
  }
}

export interface GridSums {
  count: number;
  sums: Readonly<Record<string, number>>;
}

export function sumRows<TData>(
  rows: readonly TData[],
  columns: readonly GridColumn<TData>[],
): GridSums {
  const sums: Record<string, number> = {};

  for (const column of columns) {
    sums[column.id] = rows.reduce((total, row) => {
      const value = column.value(row);

      return typeof value === 'number' ? total + value : total;
    }, 0);
  }

  return { count: rows.length, sums };
}

export type GridDisplayRow<TData> =
  | { kind: 'data'; id: string; row: TData }
  | { kind: 'subtotal'; id: string; group: GridValue; groupRow: TData; totals: GridSums }
  | { kind: 'total'; id: string; totals: GridSums };

interface DisplayRowsOptions<TData> {
  rows: readonly { id: string; row: TData }[];
  totaled: readonly GridColumn<TData>[];
  // Columna de subtotal: las filas ya vienen ordenadas por ella primero.
  subtotal?: GridColumn<TData> | undefined;
}

// Filas como las muestra la ALV con Σ y subtotales: cada grupo de valores iguales de la
// columna de subtotal termina en su fila de subtotal, y el total general va al final.
export function buildDisplayRows<TData>({ rows, totaled, subtotal }: DisplayRowsOptions<TData>): GridDisplayRow<TData>[] {
  if (totaled.length === 0) {
    return rows.map(({ id, row }) => ({ kind: 'data', id, row }));
  }

  const display: GridDisplayRow<TData>[] = [];

  if (subtotal !== undefined) {
    let group: { id: string; row: TData }[] = [];

    const closeGroup = () => {
      const first = group[0];

      if (first === undefined) {
        return;
      }

      const value = subtotal.value(first.row);

      display.push({
        kind: 'subtotal',
        id: `subtotal:${first.id}`,
        group: value,
        groupRow: first.row,
        totals: sumRows(
          group.map((item) => item.row),
          totaled,
        ),
      });
      group = [];
    };

    for (const item of rows) {
      const previous = group[0];

      if (previous !== undefined && filterKey(subtotal.type, subtotal.value(previous.row)) !== filterKey(subtotal.type, subtotal.value(item.row))) {
        closeGroup();
      }

      display.push({ kind: 'data', id: item.id, row: item.row });
      group.push(item);
    }

    closeGroup();
  } else {
    display.push(...rows.map(({ id, row }): GridDisplayRow<TData> => ({ kind: 'data', id, row })));
  }

  display.push({
    kind: 'total',
    id: 'total',
    totals: sumRows(
      rows.map((item) => item.row),
      totaled,
    ),
  });

  return display;
}
