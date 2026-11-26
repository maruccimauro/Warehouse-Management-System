import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { jsonResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

// Datos del artículo en un depósito (DM-21): stock mínimo (ZW04, ZW42 y ZW43) y umbrales de
// aprobación de ajustes (ZW35, ZW36 y ZW37), separados del maestro del catálogo.

const central = { id: 1, code: 'CENTRAL', name: 'Depósito central' };
const norte = { id: 2, code: 'NORTE', name: 'Depósito norte' };

const supervisor = testUser({ permissions: ['catalog.min_stock.manage', 'warehouses.view_all'], warehouses: [central] });
const controller = testUser({ permissions: ['inventory.thresholds.manage', 'warehouses.view_all'], warehouses: [] });

const product = {
  id: 7,
  sku: 'TOR-M6-30',
  barcode: null,
  name: 'Tornillo M6',
  description: null,
  category_id: null,
  base_uom_id: 1,
  weight_grams: null,
  length_mm: null,
  width_mm: null,
  height_mm: null,
  status: 'active',
  is_lot_tracked: false,
};

function row(overrides: Record<string, unknown> = {}) {
  return {
    product_id: 7,
    sku: 'TOR-M6-30',
    name: 'Tornillo M6',
    status: 'active',
    warehouse_id: 1,
    min_stock: 0,
    adjustment_in_approval_threshold: null,
    adjustment_out_approval_threshold: null,
    ...overrides,
  };
}

function setting(overrides: Record<string, unknown> = {}) {
  return {
    product_id: 7,
    warehouse_id: 1,
    min_stock: 0,
    adjustment_in_approval_threshold: null,
    adjustment_out_approval_threshold: null,
    created_at: '2026-11-26T10:00:00.000000Z',
    updated_at: '2026-11-26T10:00:00.000000Z',
    ...overrides,
  };
}

function page(items: unknown[]) {
  return jsonResponse(200, { data: items, meta: { current_page: 1, last_page: 1, per_page: 50, total: items.length } });
}

const warehouses = () =>
  page([
    { ...central, address: null, is_active: true },
    { ...norte, address: null, is_active: true },
  ]);

function statusBar() {
  return within(screen.getByRole('contentinfo', { name: 'Barra de estado' }));
}

// La primera transacción que abre el archivo descarga su chunk diferido.
async function fill(label: string, text: string) {
  const field = await screen.findByRole('textbox', { name: label }, { timeout: 5000 });

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

describe('ZW04: gestionar stock mínimo', () => {
  it('lista los artículos del depósito, abre la ficha y graba solo el stock mínimo', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses': warehouses,
      'GET /api/v1/warehouses/1/product-settings': () => page([row({ min_stock: 10 })]),
      'PUT /api/v1/products/7/warehouse-settings/1/min-stock': () => jsonResponse(200, { data: setting({ min_stock: 25 }) }),
    });

    renderRoute('/t/ZW04');
    await fill('Depósito', 'central');
    await fill('SKU', 'tor*');
    await userEvent.keyboard('{F8}');

    const cell = await screen.findByRole('gridcell', { name: 'TOR-M6-30' });
    expect(api.requestsTo('GET', '/api/v1/warehouses/1/product-settings')[0]?.query.get('filter[sku][0][low]')).toBe('TOR*');
    // La gestión del stock mínimo no muestra los umbrales: son de otro responsable.
    expect(screen.queryByRole('columnheader', { name: /Umbral/ })).not.toBeInTheDocument();

    await userEvent.dblClick(cell);
    expect(await screen.findByRole('textbox', { name: 'Stock mínimo' })).toHaveAttribute('readonly');

    await userEvent.click(screen.getByRole('button', { name: 'Modificar' }));
    await fill('Stock mínimo', '25');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Stock mínimo del artículo TOR-M6-30 en CENTRAL grabado.');
    });
    expect(api.requestsTo('PUT', '/api/v1/products/7/warehouse-settings/1/min-stock')[0]?.body).toEqual({ min_stock: 25 });
    expect(await screen.findByRole('textbox', { name: 'Stock mínimo' })).toHaveValue('25');
  });

  it('ofrece solo los depósitos donde el supervisor opera', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(supervisor),
      'GET /api/v1/warehouses': warehouses,
    });

    renderRoute('/t/ZW04');
    await fill('Depósito', 'norte');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('VALUE_NOT_FOUND');
    expect(screen.queryByRole('grid')).not.toBeInTheDocument();
  });
});

describe('ZW36: modificar umbrales de ajuste', () => {
  it('lee los umbrales del depósito, graba vacío como sin umbral y vuelve a la pantalla inicial', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(controller),
      'GET /api/v1/warehouses': warehouses,
      'GET /api/v1/products': () => page([product]),
      'GET /api/v1/products/7/warehouse-settings': () =>
        jsonResponse(200, { data: [setting({ warehouse_id: 2, adjustment_in_approval_threshold: 100, adjustment_out_approval_threshold: 5 })] }),
      'PUT /api/v1/products/7/warehouse-settings/2/adjustment-thresholds': () =>
        jsonResponse(200, { data: setting({ warehouse_id: 2, adjustment_out_approval_threshold: 5 }) }),
    });

    // Control central: modifica en un depósito que ve aunque no opere en ninguno.
    renderRoute('/t/ZW36');
    await fill('Depósito', 'norte');
    await fill('Artículo', 'tor-m6-30');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('textbox', { name: 'Umbral de aprobación de ajuste positivo' })).toHaveValue('100');
    expect(screen.getByRole('textbox', { name: 'Umbral de aprobación de ajuste negativo' })).toHaveValue('5');
    expect(screen.queryByRole('textbox', { name: 'Stock mínimo' })).not.toBeInTheDocument();

    await userEvent.clear(screen.getByRole('textbox', { name: 'Umbral de aprobación de ajuste positivo' }));
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(api.requestsTo('PUT', '/api/v1/products/7/warehouse-settings/2/adjustment-thresholds')[0]?.body).toEqual({
        adjustment_in_approval_threshold: null,
        adjustment_out_approval_threshold: 5,
      });
    });
    expect(await screen.findByRole('heading', { name: 'Modificar umbrales de ajuste', level: 2 })).toBeInTheDocument();
  });

  it('rechaza un umbral de cero, que no es un umbral válido', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(controller),
      'GET /api/v1/warehouses': warehouses,
      'GET /api/v1/products': () => page([product]),
      'GET /api/v1/products/7/warehouse-settings': () => jsonResponse(200, { data: [] }),
    });

    renderRoute('/t/ZW36');
    await fill('Depósito', 'central');
    await fill('Artículo', 'tor-m6-30');
    await userEvent.keyboard('{F8}');

    await fill('Umbral de aprobación de ajuste negativo', '0');
    await userEvent.keyboard('{Control>}s{/Control}');

    expect(await statusBar().findByRole('alert')).toBeInTheDocument();
    expect(api.requestsTo('PUT', '/api/v1/products/7/warehouse-settings/1/adjustment-thresholds')).toHaveLength(0);
  });

  it('informa un artículo inexistente', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(controller),
      'GET /api/v1/warehouses': warehouses,
      'GET /api/v1/products': () => page([]),
    });

    renderRoute('/t/ZW37');
    await fill('Depósito', 'central');
    await fill('Artículo', 'nada');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('El artículo NADA no existe.');
  });
});
