import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { jsonResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

// Pruebas de componente de E2.12 sobre el armazón completo: depósito, ubicaciones y bloqueo.

const central = { id: 1, code: 'CENTRAL', name: 'Depósito central' };

const supervisor = testUser({
  permissions: ['locations.manage', 'locations.block'],
  warehouses: [central],
});

const zones = [
  { id: 10, warehouse_id: 1, code: 'ALM', name: 'Almacenamiento', type: 'storage', is_system: false },
  { id: 11, warehouse_id: 1, code: 'RECEPCION', name: 'Recepción', type: 'receiving', is_system: true },
];

function location(overrides: Record<string, unknown> = {}) {
  return {
    id: 5,
    warehouse_id: 1,
    zone_id: 10,
    code: 'A-01-01',
    aisle: 'A',
    rack: '01',
    level: '01',
    position: null,
    status: 'active',
    status_reason: null,
    max_units: null,
    system_role: null,
    is_system: false,
    ...overrides,
  };
}

function page(items: unknown[]) {
  return jsonResponse(200, { data: items, meta: { current_page: 1, last_page: 1, per_page: 200, total: items.length } });
}

function statusBar() {
  return within(screen.getByRole('contentinfo', { name: 'Barra de estado' }));
}

async function fill(label: string, text: string) {
  const field = await screen.findByRole('textbox', { name: label });

  await userEvent.clear(field);
  await userEvent.type(field, text);
  await userEvent.tab();
}

beforeEach(() => {
  localStorage.clear();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('LS10: generar ubicaciones', () => {
  async function fillTemplate(aisleTo: string, levelTo: string) {
    await fill('Depósito', 'central');
    await fill('Zona', 'alm');
    await fill('Pasillo desde', 'a');
    await fill('Pasillo hasta', aisleTo);
    await fill('Estantería desde', '1');
    await fill('Estantería hasta', '10');
    await fill('Nivel desde', '1');
    await fill('Nivel hasta', levelTo);
  }

  it('muestra cuántas crea, pide confirmación y envía la plantilla', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses/1/zones': () => page(zones),
      'POST /api/v1/warehouses/1/locations/bulk': () => jsonResponse(201, { data: { created: 160 } }),
    });

    renderRoute('/t/LS10');
    await fillTemplate('d', '4');

    expect(screen.getByText('Se crean 160 ubicaciones, de A-01-01 a D-10-04.')).toBeInTheDocument();

    await userEvent.keyboard('{F8}');

    const dialog = await screen.findByRole('dialog', { name: 'Generar ubicaciones' });

    expect(dialog).toHaveTextContent('Se van a crear 160 ubicaciones en la zona ALM, de A-01-01 a D-10-04.');

    await userEvent.click(within(dialog).getByRole('button', { name: 'Generar' }));

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Se crearon 160 ubicaciones en la zona ALM.');
    });
    expect(api.requestsTo('POST', '/api/v1/warehouses/1/locations/bulk')[0]?.body).toEqual({
      zone_id: 10,
      aisles: { from: 'A', to: 'D' },
      racks: { from: 1, to: 10 },
      levels: { from: 1, to: 4 },
      positions: null,
      max_units: null,
    });
  });

  it('una plantilla sobre el límite no se envía y el mensaje enlaza a su explicación', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses/1/zones': () => page(zones),
    });

    renderRoute('/t/LS10');
    await fillTemplate('z', '9');
    await userEvent.keyboard('{F8}');

    const alert = await statusBar().findByRole('alert');

    expect(alert).toHaveTextContent('La plantilla genera 2.340 ubicaciones y el máximo por lote es 2.000.');
    expect(statusBar().getByRole('link', { name: /LOCATION_BATCH_TOO_LARGE/ })).toHaveAttribute(
      'href',
      '/docs/referencia/mensajes#location-batch-too-large',
    );
    expect(api.requestsTo('POST', '/api/v1/warehouses/1/locations/bulk')).toHaveLength(0);
  });
});

describe('LX03: lista de ubicaciones', () => {
  it('envía el patrón como CP y filtra por la zona elegida en el árbol', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses': () => page([{ ...central, address: null, is_active: true }]),
      'GET /api/v1/warehouses/1/zones': () => page(zones),
      'GET /api/v1/warehouses/1/locations': () => page([location()]),
    });

    renderRoute('/t/LX03');
    await fill('Depósito', 'central');
    await fill('Ubicación', 'a-01*');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('gridcell', { name: 'A-01-01' })).toBeInTheDocument();

    const [first] = api.requestsTo('GET', '/api/v1/warehouses/1/locations');

    expect(first?.query.get('filter[code][0][option]')).toBe('CP');
    expect(first?.query.get('filter[code][0][low]')).toBe('A-01*');

    await userEvent.click(await screen.findByRole('treeitem', { name: /^ALM/ }));

    await waitFor(() => {
      expect(api.requestsTo('GET', '/api/v1/warehouses/1/locations').at(-1)?.query.getAll('filter[zone_id][]')).toEqual(['10']);
    });
  });
});

describe('ZW05 y LS02N: pantalla inicial y bloqueo', () => {
  it('bloquea una ubicación activa con motivo', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses/1/zones': () => page(zones),
      'GET /api/v1/warehouses/1/locations': () => page([location()]),
      'POST /api/v1/locations/5/block': () => jsonResponse(200, { data: location({ status: 'blocked', status_reason: 'Estante dañado' }) }),
    });

    renderRoute('/t/ZW05');
    await fill('Depósito', 'central');
    await fill('Ubicación', 'a-01-01');
    await userEvent.keyboard('{F8}');

    await fill('Motivo del bloqueo', 'Estante dañado');
    await userEvent.click(screen.getByRole('button', { name: 'Bloquear' }));

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Ubicación A-01-01 bloqueada.');
    });
    expect(api.requestsTo('POST', '/api/v1/locations/5/block')[0]?.body).toEqual({ reason: 'Estante dañado' });
  });

  it('una ubicación inexistente no abre la ficha y el mensaje tiene código', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses/1/zones': () => page(zones),
      'GET /api/v1/warehouses/1/locations': () => page([]),
    });

    renderRoute('/t/LS02N');
    await fill('Depósito', 'central');
    await fill('Ubicación', 'x-99');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('La ubicación X-99 no existe en el depósito CENTRAL.');
    expect(statusBar().getByRole('link', { name: /LOCATION_NOT_FOUND/ })).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Zona' })).not.toBeInTheDocument();
  });

  it('F3 desde la ficha con cambios sin grabar pide confirmación antes de volver', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses/1/zones': () => page(zones),
      'GET /api/v1/warehouses/1/locations': () => page([location()]),
    });

    renderRoute('/t/LS02N');
    await fill('Depósito', 'central');
    await fill('Ubicación', 'a-01-01');
    await userEvent.keyboard('{F8}');
    await fill('Capacidad en unidades', '40');
    await userEvent.keyboard('{F3}');

    expect(await screen.findByRole('dialog', { name: 'Salir sin grabar' })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Capacidad en unidades' })).toHaveValue('40');

    await userEvent.click(screen.getByRole('button', { name: 'Salir sin grabar' }));

    expect(await screen.findByRole('heading', { name: 'Modificar ubicación', level: 2 })).toBeInTheDocument();
  });
});
