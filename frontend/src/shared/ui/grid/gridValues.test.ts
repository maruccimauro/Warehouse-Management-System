import { listPageSchema, listSearchParams } from '@/shared/api/listing';
import { z } from 'zod';
import { csvCell, csvFileName, csvSeparator, toCsv } from './csv';
import type { GridColumn } from './gridTypes';
import { buildDisplayRows, compareGridValues, matchesFilter } from './gridValues';

interface Item {
  sku: string;
  zone: string;
  quantity: number;
  active: boolean;
}

const sku: GridColumn<Item> = { id: 'sku', header: 'SKU', value: (row) => row.sku };
const zone: GridColumn<Item> = { id: 'zone', header: 'Zona', value: (row) => row.zone };
const quantity: GridColumn<Item> = {
  id: 'quantity',
  header: 'Cantidad',
  type: 'number',
  summable: true,
  value: (row) => row.quantity,
};
const active: GridColumn<Item> = {
  id: 'active',
  header: 'Activo',
  value: (row) => row.active,
  format: (value) => (value === true ? 'Sí' : 'No'),
};

const items: Item[] = [
  { sku: 'A-10', zone: 'Z1', quantity: 5, active: true },
  { sku: 'A-9', zone: 'Z1', quantity: 7, active: false },
  { sku: 'B-1', zone: 'Z2', quantity: 1200, active: true },
];

describe('compareGridValues', () => {
  it('ordena texto con orden numérico y sin distinguir mayúsculas ni acentos', () => {
    expect(['A-10', 'a-9', 'Ñandú', 'Nube'].sort((a, b) => compareGridValues('text', a, b))).toEqual([
      'a-9',
      'A-10',
      'Nube',
      'Ñandú',
    ]);
  });

  it('compara números y fechas como valores y deja los vacíos al final', () => {
    expect([10, null, 9, 100].sort((a, b) => compareGridValues('number', a, b))).toEqual([9, 10, 100, null]);
    expect(
      ['2026-11-24T10:00:00Z', '2026-01-02T00:00:00Z'].sort((a, b) => compareGridValues('datetime', a, b)),
    ).toEqual(['2026-01-02T00:00:00Z', '2026-11-24T10:00:00Z']);
  });
});

describe('matchesFilter', () => {
  it('contains no distingue mayúsculas ni acentos, como ILIKE', () => {
    expect(matchesFilter(sku, 'Tornillo AÑO', { operator: 'contains', values: ['ano'] })).toBe(true);
    expect(matchesFilter(sku, 'Tuerca', { operator: 'contains', values: ['torn'] })).toBe(false);
  });

  it('equals compara exacto y los booleanos como 1 y 0', () => {
    expect(matchesFilter(quantity, 7, { operator: 'equals', values: ['7'] })).toBe(true);
    expect(matchesFilter(quantity, 70, { operator: 'equals', values: ['7'] })).toBe(false);
    expect(matchesFilter(active, false, { operator: 'equals', values: ['0'] })).toBe(true);
  });

  it('in acepta cualquiera de los valores', () => {
    expect(matchesFilter(zone, 'Z2', { operator: 'in', values: ['Z1', 'Z2'] })).toBe(true);
    expect(matchesFilter(zone, 'Z3', { operator: 'in', values: ['Z1', 'Z2'] })).toBe(false);
  });
});

describe('buildDisplayRows', () => {
  const rows = items.map((row) => ({ id: row.sku, row }));

  it('sin totales devuelve solo las filas de datos', () => {
    expect(buildDisplayRows({ rows, totaled: [] }).map((row) => row.kind)).toEqual(['data', 'data', 'data']);
  });

  it('agrega el subtotal al cierre de cada grupo y el total general al final', () => {
    const display = buildDisplayRows({ rows, totaled: [quantity], subtotal: zone });

    expect(display.map((row) => row.kind)).toEqual(['data', 'data', 'subtotal', 'data', 'subtotal', 'total']);
    expect(display[2]).toMatchObject({ kind: 'subtotal', group: 'Z1', totals: { count: 2, sums: { quantity: 12 } } });
    expect(display[5]).toMatchObject({ kind: 'total', totals: { count: 3, sums: { quantity: 1212 } } });
  });
});

describe('CSV', () => {
  it('usa punto y coma con coma decimal y coma con punto decimal', () => {
    expect(csvSeparator('es-AR')).toBe(';');
    expect(csvSeparator('en-US')).toBe(',');
  });

  it('neutraliza fórmulas y entrecomilla separadores, comillas y saltos de línea', () => {
    expect(csvCell('=HYPERLINK("http://x")', ';')).toBe('"\'=HYPERLINK(""http://x"")"');
    expect(csvCell('-2+3+cmd|calc', ';')).toBe("'-2+3+cmd|calc");
    expect(csvCell('@SUM(A1)', ';')).toBe("'@SUM(A1)");
    expect(csvCell('a;b', ';')).toBe('"a;b"');
    expect(csvCell('línea\nsiguiente', ';')).toBe('"línea\nsiguiente"');
    // Un negativo formateado por la grilla es un número, no una fórmula.
    expect(csvCell('-5', ';', true)).toBe('-5');
  });

  it('exporta con BOM, encabezados, números sin separador de miles y el texto de format', () => {
    const csv = toCsv([sku, quantity, active], items);

    expect(csv.charCodeAt(0)).toBe(0xfeff);
    expect(csv.slice(1).split('\r\n')).toEqual([
      'SKU;Cantidad;Activo',
      'A-10;5;Sí',
      'A-9;7;No',
      'B-1;1200;Sí',
      '',
    ]);
  });

  it('nombra el archivo con la fecha y la hora', () => {
    expect(csvFileName('productos', new Date(2026, 10, 24, 9, 5))).toBe('productos-20261124-0905.csv');
  });
});

describe('parámetros de listado (E2.10)', () => {
  it('arma filter, filter[] para selección múltiple, sort descendente y la página', () => {
    const search = listSearchParams({
      page: 2,
      perPage: 50,
      sort: { field: 'code', desc: true },
      filters: { name: 'torn', status: ['active', 'blocked'] },
    });

    expect(search.getAll('filter[status][]')).toEqual(['active', 'blocked']);
    expect(search.get('filter[name]')).toBe('torn');
    expect(search.get('sort')).toBe('-code');
    expect(search.get('page')).toBe('2');
    expect(search.get('per_page')).toBe('50');
  });

  it('lee la respuesta paginada de Laravel', () => {
    const page = listPageSchema(z.object({ id: z.number() })).parse({
      data: [{ id: 1 }],
      links: {},
      meta: { current_page: 1, last_page: 3, per_page: 1, total: 3, from: 1, to: 1 },
    });

    expect(page).toEqual({ items: [{ id: 1 }], page: 1, lastPage: 3, perPage: 1, total: 3 });
  });
});
