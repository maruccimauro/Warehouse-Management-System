import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { jsonResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

// Pruebas de componente de E3.27: contador del encabezado y SBWP Bandeja de entrada.

const operator = testUser({ username: 'OPER', permissions: ['warehouses.view_all'] });

function message(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    type: 'test.notice',
    parameters: {},
    subject: null,
    read_at: null,
    archived_at: null,
    created_at: '2026-11-26T12:00:00.000000Z',
    ...overrides,
  };
}

function page(items: unknown[]) {
  return jsonResponse(200, { data: items, meta: { current_page: 1, last_page: 1, per_page: 50, total: items.length } });
}

function rowOf(row: HTMLElement | undefined): HTMLElement {
  if (row === undefined) {
    throw new Error('Falta la fila esperada.');
  }

  return row;
}

function statusBar() {
  return within(screen.getByRole('contentinfo', { name: 'Barra de estado' }));
}

// Espera la grilla con sus filas: la primera apertura descarga el chunk diferido.
async function inboxGrid(name = 'Entrada') {
  const grid = await screen.findByRole('grid', { name }, { timeout: 5000 });

  await waitFor(() => {
    expect(within(grid).queryByText('Cargando datos…')).not.toBeInTheDocument();
  });

  return grid;
}

beforeEach(() => {
  localStorage.clear();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('contador de no leídos', () => {
  it('muestra los no leídos en el encabezado y abre la bandeja', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(operator),
      'GET /api/v1/me/inbox/unread-count': () => jsonResponse(200, { data: { unread: 3 } }),
      'GET /api/v1/me/inbox': () => page([message()]),
    });
    renderRoute('/');

    const counter = await screen.findByRole('link', { name: 'Bandeja de entrada: 3 mensajes no leídos' });
    expect(counter).toHaveTextContent('3');

    await userEvent.click(counter);

    expect(await screen.findByRole('heading', { name: /Bandeja de entrada/ }, { timeout: 5000 })).toBeInTheDocument();
  });

  it('no muestra número sin mensajes no leídos', async () => {
    stubApi({ 'GET /api/v1/auth/me': () => userResponse(operator) });
    renderRoute('/');

    const counter = await screen.findByRole('link', { name: 'Bandeja de entrada: sin mensajes no leídos' });
    expect(counter).toHaveTextContent('');
  });
});

describe('SBWP: bandeja de entrada', () => {
  it('lista la entrada con los no leídos en negrita y pide la carpeta al backend', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(operator),
      'GET /api/v1/me/inbox': () => page([message({ id: 2 }), message({ id: 1, read_at: '2026-11-26T13:00:00.000000Z' })]),
    });
    renderRoute('/t/SBWP');

    const grid = await inboxGrid();
    const [unread, read, ...others] = within(grid).getAllByRole('row').slice(1);

    expect(others).toHaveLength(0);
    expect(within(rowOf(unread)).getByText('Aviso del sistema (test.notice).')).toHaveClass('font-semibold');
    expect(within(rowOf(unread)).getByText('No leído')).toBeInTheDocument();
    expect(within(rowOf(read)).getByText('Leído')).toBeInTheDocument();
    expect(api.requestsTo('GET', '/api/v1/me/inbox')[0]?.query.get('filter[folder]')).toBe('inbox');
    expect(api.requestsTo('GET', '/api/v1/me/inbox')[0]?.query.get('sort')).toBe('-id');
  });

  it('archiva la selección y lo informa en la barra de estado', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(operator),
      'GET /api/v1/me/inbox': () => page([message({ id: 2 }), message({ id: 1 })]),
      'PATCH /api/v1/me/inbox': () => new Response(null, { status: 204 }),
    });
    renderRoute('/t/SBWP');

    const grid = await inboxGrid();
    const archive = screen.getByRole('button', { name: 'Archivar' });
    expect(archive).toBeDisabled();

    await userEvent.click(within(grid).getByRole('checkbox', { name: 'Seleccionar todas las filas visibles' }));
    await userEvent.click(archive);

    await waitFor(() => {
      expect(api.requestsTo('PATCH', '/api/v1/me/inbox')[0]?.body).toEqual({ ids: [2, 1], archived: true });
    });
    expect(await statusBar().findByText('2 mensajes archivados.')).toBeInTheDocument();
  });

  it('marca leído al abrir un mensaje no leído con doble clic', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(operator),
      'GET /api/v1/me/inbox': () => page([message({ id: 7 })]),
      'PATCH /api/v1/me/inbox': () => new Response(null, { status: 204 }),
    });
    renderRoute('/t/SBWP');

    const grid = await inboxGrid();
    await userEvent.dblClick(within(grid).getByText('Aviso del sistema (test.notice).'));

    await waitFor(() => {
      expect(api.requestsTo('PATCH', '/api/v1/me/inbox')[0]?.body).toEqual({ ids: [7], read: true });
    });
  });

  it('muestra los archivados con Desarchivar', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(operator),
      'GET /api/v1/me/inbox': (request) =>
        page(request.query.get('filter[folder]') === 'archived' ? [message({ id: 3, read_at: '2026-11-26T13:00:00.000000Z', archived_at: '2026-11-26T14:00:00.000000Z' })] : []),
    });
    renderRoute('/t/SBWP');

    await inboxGrid();
    await userEvent.click(screen.getByRole('tab', { name: 'Archivados' }));

    const grid = await inboxGrid('Archivados');
    expect(within(grid).getAllByRole('row').slice(1)).toHaveLength(1);
    expect(screen.getByRole('button', { name: 'Desarchivar' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Archivar' })).not.toBeInTheDocument();
    expect(api.requestsTo('GET', '/api/v1/me/inbox').some((request) => request.query.get('filter[folder]') === 'archived')).toBe(true);
  });
});
