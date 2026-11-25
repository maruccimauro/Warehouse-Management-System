import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ListPage, ListParams } from '@/shared/api/listing';
import { DataGrid, type DataGridProps } from './DataGrid';
import type { GridColumn } from './gridTypes';

interface Product {
  id: number;
  sku: string;
  name: string;
  category: string;
  stock: number;
  status: 'active' | 'blocked';
}

const statusOptions = [
  { value: 'active', label: 'Activo' },
  { value: 'blocked', label: 'Bloqueado' },
];

const products: Product[] = [
  { id: 1, sku: 'TOR-10', name: 'Tornillo largo', category: 'Fijaciones', stock: 1500, status: 'active' },
  { id: 2, sku: 'TOR-9', name: 'Tornillo corto', category: 'Fijaciones', stock: 20, status: 'blocked' },
  { id: 3, sku: 'TUE-1', name: 'Tuerca', category: 'Fijaciones', stock: 300, status: 'active' },
  { id: 4, sku: 'PIN-1', name: 'Pintura', category: 'Pinturas', stock: 7, status: 'active' },
];

const columns: GridColumn<Product>[] = [
  { id: 'sku', header: 'SKU', value: (row) => row.sku },
  { id: 'name', header: 'Nombre', value: (row) => row.name },
  { id: 'category', header: 'Categoría', value: (row) => row.category },
  { id: 'stock', header: 'Stock', type: 'number', summable: true, value: (row) => row.stock },
  {
    id: 'status',
    header: 'Estado',
    value: (row) => row.status,
    options: statusOptions,
    format: (value) => statusOptions.find((option) => option.value === value)?.label ?? '',
  },
];

function renderGrid(props: Partial<DataGridProps<Product>> = {}) {
  const onMessage = vi.fn();
  const onRowActivate = vi.fn();
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  render(
    <QueryClientProvider client={client}>
      <DataGrid
        label="Productos"
        columns={columns}
        source={{ kind: 'local', rows: products }}
        rowKey={(row) => row.id}
        exportName="productos"
        onMessage={onMessage}
        onRowActivate={onRowActivate}
        {...props}
      />
    </QueryClientProvider>,
  );

  return { onMessage, onRowActivate, grid: screen.getByRole('grid', { name: 'Productos' }) };
}

function bodyRows(grid: HTMLElement): string[][] {
  const [, ...rows] = within(grid).getAllByRole('row');

  return rows.map((row) => within(row).getAllByRole('gridcell').map((cell) => cell.textContent));
}

function columnOf(grid: HTMLElement, index: number): string[] {
  return bodyRows(grid).map((row) => row[index] ?? '');
}

function cell(grid: HTMLElement, text: string): HTMLElement {
  return within(grid).getByRole('gridcell', { name: text });
}

function firstOf(elements: HTMLElement[]): HTMLElement {
  const [first] = elements;

  if (first === undefined) {
    throw new Error('No se encontró ningún elemento.');
  }

  return first;
}

// Captura el CSV que la grilla entrega al navegador.
function captureDownloads() {
  const blobs: Blob[] = [];

  vi.spyOn(URL, 'createObjectURL').mockImplementation((blob) => {
    blobs.push(blob as Blob);

    return 'blob:csv';
  });
  vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined);
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

  return blobs;
}

describe('DataGrid en modo local', () => {
  it('muestra las filas con formato regional y la cantidad en el pie', () => {
    const { grid } = renderGrid();

    expect(within(grid).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'SKU',
      'Nombre',
      'Categoría',
      'Stock',
      'Estado',
    ]);
    expect(columnOf(grid, 3)).toEqual(['1.500', '20', '300', '7']);
    expect(columnOf(grid, 4)).toEqual(['Activo', 'Bloqueado', 'Activo', 'Activo']);
    expect(screen.getByText('4 filas')).toBeInTheDocument();
  });

  it('ordena desde la cabecera: ascendente, luego descendente, con aria-sort y anuncio', async () => {
    const user = userEvent.setup();
    const { grid } = renderGrid();
    const header = within(grid).getByRole('button', { name: 'SKU' });

    await user.click(header);

    expect(columnOf(grid, 0)).toEqual(['PIN-1', 'TOR-9', 'TOR-10', 'TUE-1']);
    expect(within(grid).getByRole('columnheader', { name: /SKU/ })).toHaveAttribute('aria-sort', 'ascending');
    expect(screen.getByRole('status')).toHaveTextContent('Ordenado por SKU, ascendente');

    await user.click(header);

    expect(columnOf(grid, 0)).toEqual(['TUE-1', 'TOR-10', 'TOR-9', 'PIN-1']);
    expect(within(grid).getByRole('columnheader', { name: /SKU/ })).toHaveAttribute('aria-sort', 'descending');
    expect(header).toHaveFocus();
  });

  it('ordena por número como valor y no como texto', async () => {
    const user = userEvent.setup();
    const { grid } = renderGrid();

    await user.click(within(grid).getByRole('button', { name: 'Stock' }));

    expect(columnOf(grid, 3)).toEqual(['7', '20', '300', '1.500']);
  });

  it('filtra desde el menú contextual de la celda y quita el filtro desde la lista de activos', async () => {
    const user = userEvent.setup();
    const { grid } = renderGrid();

    await user.pointer({ keys: '[MouseRight]', target: cell(grid, 'Tuerca') });
    await user.click(within(screen.getByRole('menu', { name: 'Funciones de la columna Nombre' })).getByRole('menuitem', { name: 'Definir filtro' }));

    const dialog = screen.getByRole('dialog', { name: 'Filtro: Nombre' });
    await user.type(within(dialog).getByLabelText('Contiene'), 'tornillo{Enter}');

    expect(columnOf(grid, 0)).toEqual(['TOR-10', 'TOR-9']);
    expect(screen.getByText('2 de 4 filas')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Quitar filtro: Nombre contiene «tornillo»' }));

    expect(bodyRows(grid)).toHaveLength(4);
  });

  it('filtra por varios valores de una lista', async () => {
    const user = userEvent.setup();
    const { grid } = renderGrid();

    await user.click(cell(grid, 'Bloqueado'));
    await user.click(screen.getByRole('button', { name: 'Definir filtro (Estado)' }));

    const dialog = screen.getByRole('dialog', { name: 'Filtro: Estado' });
    await user.click(within(dialog).getByRole('checkbox', { name: 'Bloqueado' }));
    await user.click(within(dialog).getByRole('button', { name: 'Aplicar' }));

    expect(columnOf(grid, 0)).toEqual(['TOR-9']);
    expect(screen.getByText('Estado es Bloqueado')).toBeInTheDocument();
  });

  it('suma la columna con Σ y agrega subtotales por la columna elegida', async () => {
    const user = userEvent.setup();
    const { grid } = renderGrid();

    await user.click(cell(grid, '1.500'));
    await user.click(screen.getByRole('button', { name: 'Total (Stock)' }));

    // El rótulo va en la primera columna visible y la suma, bajo su columna.
    expect(bodyRows(grid).at(-1)).toEqual(['Total', '', '', '1.827', '']);

    await user.click(cell(grid, 'Pinturas'));
    await user.click(screen.getByRole('button', { name: 'Subtotales (Categoría)' }));

    const rows = bodyRows(grid);

    expect(rows.map((row) => row[3])).toEqual(['1.500', '20', '300', '1.820', '7', '7', '1.827']);
    expect(rows[3]?.[0]).toBe('Subtotal Fijaciones');
    expect(rows[5]?.[0]).toBe('Subtotal Pinturas');
  });

  it('navega con flechas, Inicio y Fin, y activa la fila con Enter y doble clic', async () => {
    const user = userEvent.setup();
    const { grid, onRowActivate } = renderGrid();

    await user.click(cell(grid, 'TOR-10'));
    await user.keyboard('{ArrowDown}');
    expect(cell(grid, 'TOR-9')).toHaveFocus();

    await user.keyboard('{ArrowRight}');
    expect(cell(grid, 'Tornillo corto')).toHaveFocus();

    await user.keyboard('{End}');
    expect(within(grid).getAllByRole('gridcell', { name: 'Bloqueado' })[0]).toHaveFocus();

    await user.keyboard('{Control>}{Home}{/Control}');
    expect(within(grid).getByRole('button', { name: 'SKU' })).toHaveFocus();

    await user.keyboard('{ArrowDown}{Enter}');
    expect(onRowActivate).toHaveBeenCalledWith(products[0]);

    await user.dblClick(cell(grid, 'Pintura'));
    expect(onRowActivate).toHaveBeenLastCalledWith(products[3]);
  });

  it('deja una sola parada de Tab en la grilla', async () => {
    const user = userEvent.setup();
    const { grid } = renderGrid();

    await user.click(cell(grid, 'Tuerca'));

    const focusable = grid.querySelectorAll('[tabindex="0"]');

    expect(focusable).toHaveLength(1);
    expect(focusable[0]).toBe(cell(grid, 'Tuerca'));
  });

  it('selecciona varias filas con la casilla y con Espacio', async () => {
    const user = userEvent.setup();
    const onSelectionChange = vi.fn();
    const { grid } = renderGrid({ selection: 'multiple', onSelectionChange });

    expect(grid).toHaveAttribute('aria-multiselectable', 'true');

    await user.click(firstOf(within(grid).getAllByRole('checkbox', { name: 'Seleccionar fila' })));
    await user.click(cell(grid, 'Pintura'));
    await user.keyboard(' ');

    expect(onSelectionChange).toHaveBeenLastCalledWith([products[0], products[3]]);
    expect(within(grid).getAllByRole('row').filter((row) => row.getAttribute('aria-selected') === 'true')).toHaveLength(2);

    await user.click(within(grid).getByRole('checkbox', { name: 'Seleccionar todas las filas visibles' }));
    expect(onSelectionChange).toHaveBeenLastCalledWith(products);
  });

  it('oculta y reordena columnas desde Modificar disposición', async () => {
    const user = userEvent.setup();
    const { grid } = renderGrid();

    await user.click(screen.getByRole('button', { name: 'Modificar disposición' }));

    const dialog = screen.getByRole('dialog', { name: 'Modificar disposición' });
    await user.click(within(dialog).getByRole('checkbox', { name: 'Nombre' }));
    await user.click(within(dialog).getByRole('button', { name: 'Subir Stock' }));
    await user.click(within(dialog).getByRole('button', { name: 'Aplicar' }));

    expect(within(grid).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'SKU',
      'Stock',
      'Categoría',
      'Estado',
    ]);
  });

  it('no permite dejar la disposición sin columnas visibles', async () => {
    const user = userEvent.setup();
    renderGrid();

    await user.click(screen.getByRole('button', { name: 'Modificar disposición' }));

    const dialog = screen.getByRole('dialog', { name: 'Modificar disposición' });
    for (const name of ['SKU', 'Nombre', 'Categoría', 'Stock', 'Estado']) {
      await user.click(within(dialog).getByRole('checkbox', { name }));
    }

    expect(within(dialog).getByRole('alert')).toHaveTextContent('Tiene que quedar al menos una columna visible.');
    expect(within(dialog).getByRole('button', { name: 'Aplicar' })).toBeDisabled();
  });

  it('exporta a CSV las filas filtradas, en el orden y con las columnas visibles', async () => {
    const user = userEvent.setup();
    const blobs = captureDownloads();
    const { grid, onMessage } = renderGrid();

    await user.click(cell(grid, 'Bloqueado'));
    await user.click(screen.getByRole('button', { name: 'Definir filtro (Estado)' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('checkbox', { name: 'Activo' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Aplicar' }));
    await user.click(screen.getByRole('button', { name: 'Exportar a hoja de cálculo' }));

    await waitFor(() => {
      expect(onMessage).toHaveBeenCalledWith({ type: 'S', text: 'Se exportaron 3 filas.' });
    });

    const text = await blobs[0]?.text();

    expect(text?.split('\r\n')).toEqual([
      'SKU;Nombre;Categoría;Stock;Estado',
      'TOR-10;Tornillo largo;Fijaciones;1500;Activo',
      'TUE-1;Tuerca;Fijaciones;300;Activo',
      'PIN-1;Pintura;Pinturas;7;Activo',
      '',
    ]);
  });
});

function serverSource(fetchPage: (params: ListParams) => ListPage<Product>) {
  const calls: ListParams[] = [];
  const source = {
    kind: 'server' as const,
    queryKey: ['products'],
    fetchPage: (params: ListParams) => {
      calls.push(params);

      return Promise.resolve(fetchPage(params));
    },
  };

  return { source, calls };
}

// En modo servidor solo se ordena y filtra por lo que el listado del backend declara.
const serverColumns: GridColumn<Product>[] = [
  { id: 'sku', header: 'SKU', value: (row) => row.sku, sortable: true, filter: 'contains' },
  { id: 'name', header: 'Nombre', value: (row) => row.name },
  { id: 'stock', header: 'Stock', type: 'number', summable: true, value: (row) => row.stock },
  {
    id: 'status',
    header: 'Estado',
    field: 'status',
    value: (row) => row.status,
    options: statusOptions,
    filter: 'in',
    format: (value) => statusOptions.find((option) => option.value === value)?.label ?? '',
  },
];

function pageOf(params: ListParams, total: number): ListPage<Product> {
  const start = (params.page - 1) * params.perPage;
  const items = Array.from({ length: Math.max(0, Math.min(params.perPage, total - start)) }, (_, index) => ({
    id: start + index + 1,
    sku: `SKU-${start + index + 1}`,
    name: `Producto ${start + index + 1}`,
    category: 'General',
    stock: 1,
    status: 'active' as const,
  }));

  return { items, page: params.page, lastPage: Math.max(1, Math.ceil(total / params.perPage)), perPage: params.perPage, total };
}

describe('DataGrid en modo servidor', () => {
  it('pide la primera página y muestra el rango y el total', async () => {
    const { source, calls } = serverSource((params) => pageOf(params, 120));
    const { grid } = renderGrid({ source, columns: serverColumns });

    expect(await within(grid).findByRole('gridcell', { name: 'SKU-1' })).toBeInTheDocument();
    expect(calls[0]).toEqual({ page: 1, perPage: 50, sort: undefined, filters: {} });
    expect(screen.getByText('1–50 de 120')).toBeInTheDocument();
    expect(grid).toHaveAttribute('aria-rowcount', '121');
  });

  it('ordena y filtra solo por los campos declarados y los traduce a parámetros de la API', async () => {
    const user = userEvent.setup();
    const { source, calls } = serverSource((params) => pageOf(params, 3));
    const { grid } = renderGrid({ source, columns: serverColumns });

    await within(grid).findByRole('gridcell', { name: 'SKU-1' });

    expect(within(grid).queryByRole('button', { name: 'Nombre' })).not.toBeInTheDocument();

    await user.click(within(grid).getByRole('button', { name: 'SKU' }));
    await user.click(within(grid).getByRole('button', { name: 'SKU' }));

    await user.click(cell(grid, 'Producto 1'));
    expect(screen.getByRole('button', { name: 'Definir filtro (Nombre)' })).toHaveAttribute('aria-disabled', 'true');

    await user.click(firstOf(within(grid).getAllByRole('gridcell', { name: 'Activo' })));
    await user.click(screen.getByRole('button', { name: 'Definir filtro (Estado)' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('checkbox', { name: 'Activo' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('checkbox', { name: 'Bloqueado' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Aplicar' }));

    await waitFor(() => {
      expect(calls.at(-1)).toEqual({
        page: 1,
        perPage: 50,
        sort: { field: 'sku', desc: true },
        filters: { status: ['active', 'blocked'] },
      });
    });
  });

  it('no ofrece totales ni subtotales sobre una página', async () => {
    const { source } = serverSource((params) => pageOf(params, 3));
    const { grid } = renderGrid({ source, columns: serverColumns });

    await within(grid).findByRole('gridcell', { name: 'SKU-1' });

    expect(screen.queryByRole('button', { name: /^Total/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Subtotales/ })).not.toBeInTheDocument();
  });

  it('cambia de página y vuelve a la primera al cambiar el orden', async () => {
    const user = userEvent.setup();
    const { source, calls } = serverSource((params) => pageOf(params, 120));
    const { grid } = renderGrid({ source, columns: serverColumns });

    await within(grid).findByRole('gridcell', { name: 'SKU-1' });
    await user.click(screen.getByRole('button', { name: 'Página siguiente' }));

    expect(await within(grid).findByRole('gridcell', { name: 'SKU-51' })).toBeInTheDocument();
    expect(screen.getByText('Página 2 de 3')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Última página' }));
    expect(await screen.findByText('101–120 de 120')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Página siguiente' })).toHaveAttribute('aria-disabled', 'true');

    await user.click(within(grid).getByRole('button', { name: 'SKU' }));
    await waitFor(() => {
      expect(calls.at(-1)).toMatchObject({ page: 1, sort: { field: 'sku', desc: false } });
    });
  });

  it('exporta todas las páginas hasta el tope y avisa con W si quedaron filas afuera', async () => {
    const user = userEvent.setup();
    const blobs = captureDownloads();
    const { source, calls } = serverSource((params) => pageOf(params, 6000));
    const { grid, onMessage } = renderGrid({ source, columns: serverColumns });

    await within(grid).findByRole('gridcell', { name: 'SKU-1' });
    await user.click(screen.getByRole('button', { name: 'Exportar a hoja de cálculo' }));

    await waitFor(() => {
      expect(onMessage).toHaveBeenCalledWith({
        type: 'W',
        text: 'Se exportaron las primeras 5000 filas de 6000. Restrinja la selección para exportar el resto.',
        id: 'GRID_EXPORT_TRUNCATED',
      });
    });

    const exportCalls = calls.filter((params) => params.perPage === 200);
    const lines = (await blobs[0]?.text())?.split('\r\n') ?? [];

    expect(exportCalls).toHaveLength(25);
    // Encabezado, 5000 filas y la línea vacía del cierre.
    expect(lines).toHaveLength(5002);
  });

  it('informa el error de carga en la barra de estado y ofrece reintentar', async () => {
    const user = userEvent.setup();
    let fail = true;
    const source = {
      kind: 'server' as const,
      queryKey: ['products', 'failing'],
      fetchPage: (params: ListParams) => (fail ? Promise.reject(new Error('caída')) : Promise.resolve(pageOf(params, 1))),
    };
    const { grid, onMessage } = renderGrid({ source, columns: serverColumns });

    expect(await within(grid).findByText('No se pudieron cargar los datos.')).toBeInTheDocument();
    expect(onMessage).toHaveBeenCalledWith({ type: 'E', text: 'No se pudieron cargar los datos de la lista.' });

    fail = false;
    await user.click(within(grid).getByRole('button', { name: 'Reintentar' }));

    expect(await within(grid).findByRole('gridcell', { name: 'SKU-1' })).toBeInTheDocument();
  });
});
