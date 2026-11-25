import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { jsonResponse, problemResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

// Pruebas de componente de E2.13 sobre el armazón completo: artículos, proveedores y vínculos.

const central = { id: 1, code: 'CENTRAL', name: 'Depósito central' };

const admin = testUser({
  permissions: ['catalog.manage', 'suppliers.manage'],
  warehouses: [central],
});

const categories = [
  { id: 1, parent_id: null, name: 'Ferretería' },
  { id: 2, parent_id: 1, name: 'Tornillería' },
];

const units = [
  { id: 1, code: 'UN', name: 'Unidad' },
  { id: 2, code: 'G', name: 'Gramo' },
];

function product(overrides: Record<string, unknown> = {}) {
  return {
    id: 7,
    sku: 'TOR-M6-30',
    barcode: '7790000000010',
    name: 'Tornillo M6',
    description: null,
    category_id: 2,
    base_uom_id: 1,
    weight_grams: 9,
    length_mm: null,
    width_mm: null,
    height_mm: null,
    status: 'active',
    is_lot_tracked: false,
    ...overrides,
  };
}

const acme = { id: 3, number: '000003', tax_id: '30712345671', name: 'ACME SA', email: null, phone: null, is_active: true };

function page(items: unknown[]) {
  return jsonResponse(200, { data: items, meta: { current_page: 1, last_page: 1, per_page: 200, total: items.length } });
}

function statusBar() {
  return within(screen.getByRole('contentinfo', { name: 'Barra de estado' }));
}

// La primera transacción que abre el archivo descarga su chunk diferido: con el entorno en frío
// tarda más que la espera por defecto de findBy.
async function fill(label: string, text: string) {
  const field = await screen.findByRole('textbox', { name: label }, { timeout: 5000 });

  await userEvent.clear(field);
  await userEvent.type(field, text);
  await userEvent.tab();
}

const catalogHandlers = {
  'GET /api/v1/auth/me': () => userResponse(admin),
  'GET /api/v1/categories': () => page(categories),
  'GET /api/v1/units': () => page(units),
};

beforeEach(() => {
  localStorage.clear();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('MM01 a MM03: artículos', () => {
  it('crea un artículo con categoría, unidad y stock mínimo del depósito', async () => {
    const api = stubApi({
      ...catalogHandlers,
      'GET /api/v1/products': () => page([]),
      'POST /api/v1/products': () => jsonResponse(201, { data: product({ id: 9, sku: 'TOR-M8-40' }) }),
      'PUT /api/v1/products/9/warehouse-settings/1': () => jsonResponse(200, { data: { product_id: 9, warehouse_id: 1, min_stock: 50 } }),
    });

    renderRoute('/t/MM01');
    await fill('SKU', 'tor-m8-40');
    await fill('Depósito (stock mínimo)', 'central');
    await userEvent.keyboard('{F8}');

    await fill('Nombre', 'Tornillo M8');
    await fill('Categoría', 'ferretería/tornillería');
    await fill('Unidad base', 'un');
    await fill('Stock mínimo', '50');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Artículo TOR-M8-40 creado.');
    });
    expect(api.requestsTo('GET', '/api/v1/products')[0]?.query.get('filter[sku][0][low]')).toBe('TOR-M8-40');
    expect(api.requestsTo('POST', '/api/v1/products')[0]?.body).toMatchObject({
      sku: 'TOR-M8-40',
      name: 'Tornillo M8',
      category_id: 2,
      base_uom_id: 1,
      barcode: null,
      weight_grams: null,
    });
    expect(api.requestsTo('PUT', '/api/v1/products/9/warehouse-settings/1')[0]?.body).toEqual({ min_stock: 50 });
  });

  it('un SKU existente no abre la ficha de crear', async () => {
    stubApi({ ...catalogHandlers, 'GET /api/v1/products': () => page([product()]) });

    renderRoute('/t/MM01');
    await fill('SKU', 'tor-m6-30');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('El artículo TOR-M6-30 ya existe.');
    expect(statusBar().getByRole('link', { name: /VALUE_ALREADY_EXISTS/ })).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Nombre' })).not.toBeInTheDocument();
  });

  it('un SKU inexistente informa PRODUCT_NOT_FOUND', async () => {
    stubApi({ ...catalogHandlers, 'GET /api/v1/products': () => page([]) });

    renderRoute('/t/MM02');
    await fill('SKU', 'nada');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('El artículo NADA no existe.');
    expect(statusBar().getByRole('link', { name: /PRODUCT_NOT_FOUND/ })).toHaveAttribute('href', '/docs/referencia/mensajes#product-not-found');
  });

  it('si el artículo se graba y el stock mínimo no, avisa con un W y vuelve a la pantalla inicial', async () => {
    const api = stubApi({
      ...catalogHandlers,
      'GET /api/v1/products': () => page([product()]),
      'GET /api/v1/products/7/warehouse-settings': () => jsonResponse(200, { data: [] }),
      'PATCH /api/v1/products/7': () => jsonResponse(200, { data: product({ status: 'discontinued' }) }),
      'PUT /api/v1/products/7/warehouse-settings/1': () => problemResponse(403, 'FORBIDDEN', { detail: 'No tiene permiso para operar en este depósito.' }),
    });

    renderRoute('/t/MM02');
    await fill('SKU', 'tor-m6-30');
    await fill('Depósito (stock mínimo)', 'central');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('textbox', { name: 'Categoría' })).toHaveValue('Ferretería / Tornillería');
    expect(screen.getByRole('textbox', { name: 'Unidad base' })).toBeDisabled();

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Estado' }), 'discontinued');
    await fill('Stock mínimo', '20');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(statusBar().getByRole('link', { name: /WAREHOUSE_SETTING_NOT_SAVED/ })).toBeInTheDocument();
    });
    expect(api.requestsTo('PATCH', '/api/v1/products/7')[0]?.body).toMatchObject({ status: 'discontinued', category_id: 2 });
    expect(await screen.findByRole('heading', { name: 'Modificar catálogo', level: 2 })).toBeInTheDocument();
  });

  it('visualizar muestra la ficha en solo lectura y los proveedores del artículo', async () => {
    stubApi({
      ...catalogHandlers,
      'GET /api/v1/products': () => page([product()]),
      'GET /api/v1/products/7/suppliers': () =>
        jsonResponse(200, {
          data: [{ product_id: 7, supplier_id: 3, supplier: { number: '000003', tax_id: '30712345671', name: 'ACME SA', is_active: true }, supplier_sku: 'A-100', lead_time_days: 5 }],
        }),
    });

    renderRoute('/t/MM03');
    await fill('SKU', 'tor-m6-30');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('gridcell', { name: 'ACME SA' })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveAttribute('readonly');
    expect(screen.getByRole('textbox', { name: 'Descripción' }).tagName).toBe('TEXTAREA');
  });
});

describe('MM60: lista de artículos', () => {
  it('envía el SKU como selección y la categoría por id', async () => {
    const api = stubApi({ ...catalogHandlers, 'GET /api/v1/products': () => page([product()]) });

    renderRoute('/t/MM60');
    await fill('SKU', 'tor*');
    await fill('Categoría', 'Ferretería / Tornillería');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('gridcell', { name: 'TOR-M6-30' })).toBeInTheDocument();

    const [first] = api.requestsTo('GET', '/api/v1/products');

    expect(first?.query.get('filter[sku][0][option]')).toBe('CP');
    expect(first?.query.get('filter[sku][0][low]')).toBe('TOR*');
    expect(first?.query.get('filter[category_id]')).toBe('2');
  });
});

describe('XK01 a XK03: proveedores', () => {
  it('crea un proveedor con el identificador fiscal normalizado y deja la ficha vacía', async () => {
    const api = stubApi({
      ...catalogHandlers,
      'POST /api/v1/suppliers': () => jsonResponse(201, { data: acme }),
    });

    renderRoute('/t/XK01');
    await fill('Identificador fiscal', '30-71234567-1');
    await fill('Nombre', 'ACME SA');
    await fill('Email', 'Compras@Acme.com');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Proveedor 000003 ACME SA creado.');
    });
    expect(api.requestsTo('POST', '/api/v1/suppliers')[0]?.body).toEqual({
      tax_id: '30712345671',
      name: 'ACME SA',
      email: 'Compras@Acme.com',
      phone: null,
    });
    expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveValue('');
  });

  it('desactiva un proveedor desde modificar', async () => {
    const api = stubApi({
      ...catalogHandlers,
      'GET /api/v1/suppliers': () => page([acme]),
      'PATCH /api/v1/suppliers/3': () => jsonResponse(200, { data: { ...acme, is_active: false } }),
    });

    renderRoute('/t/XK02');
    await fill('Proveedor', '3');
    await userEvent.keyboard('{F8}');

    await userEvent.selectOptions(await screen.findByRole('combobox', { name: 'Estado' }), 'inactive');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Proveedor 000003 ACME SA grabado.');
    });
    expect(api.requestsTo('GET', '/api/v1/suppliers')[0]?.query.get('filter[number]')).toBe('000003');
    expect(api.requestsTo('PATCH', '/api/v1/suppliers/3')[0]?.body).toMatchObject({ is_active: false });
  });
});

describe('ZW06: artículos del proveedor', () => {
  async function openNewLink() {
    renderRoute('/t/ZW06');
    await fill('Proveedor', '000003');
    await userEvent.keyboard('{F8}');
    await userEvent.click(await screen.findByRole('button', { name: 'Vincular artículo' }));
  }

  it('vincula un artículo con su código y plazo', async () => {
    const api = stubApi({
      ...catalogHandlers,
      'GET /api/v1/suppliers': () => page([acme]),
      'GET /api/v1/suppliers/3/products': () => page([]),
      'GET /api/v1/products': () => page([product()]),
      'GET /api/v1/products/7/suppliers': () => jsonResponse(200, { data: [] }),
      'PUT /api/v1/suppliers/3/products/7': () =>
        jsonResponse(200, { data: { product_id: 7, supplier_id: 3, supplier_sku: 'A-100', lead_time_days: 5 } }),
    });

    await openNewLink();
    await fill('Artículo', 'tor-m6-30');
    await fill('Código del proveedor', 'A-100');
    await fill('Plazo de entrega (días)', '5');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Artículo TOR-M6-30 vinculado al proveedor.');
    });
    expect(api.requestsTo('PUT', '/api/v1/suppliers/3/products/7')[0]?.body).toEqual({ supplier_sku: 'A-100', lead_time_days: 5 });
  });

  it('no pisa un vínculo existente al vincular de nuevo el mismo artículo', async () => {
    const api = stubApi({
      ...catalogHandlers,
      'GET /api/v1/suppliers': () => page([acme]),
      'GET /api/v1/suppliers/3/products': () => page([]),
      'GET /api/v1/products': () => page([product()]),
      'GET /api/v1/products/7/suppliers': () =>
        jsonResponse(200, { data: [{ product_id: 7, supplier_id: 3, supplier_sku: 'A-100', lead_time_days: 5 }] }),
    });

    await openNewLink();
    await fill('Artículo', 'tor-m6-30');
    await userEvent.keyboard('{Control>}s{/Control}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('El artículo TOR-M6-30 ya está vinculado a este proveedor.');
    expect(api.requestsTo('PUT', '/api/v1/suppliers/3/products/7')).toHaveLength(0);
  });
});

describe('ZW61 a ZW63: transacciones individuales del artículo del proveedor', () => {
  it('ZW62 informa SUPPLIER_PRODUCT_NOT_FOUND si el artículo no está vinculado', async () => {
    stubApi({
      ...catalogHandlers,
      'GET /api/v1/suppliers': () => page([acme]),
      'GET /api/v1/products': () => page([product()]),
      'GET /api/v1/products/7/suppliers': () => jsonResponse(200, { data: [] }),
    });

    renderRoute('/t/ZW62');
    await fill('Proveedor', '3');
    await fill('Artículo', 'tor-m6-30');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('El artículo TOR-M6-30 no está vinculado al proveedor 000003.');
    expect(statusBar().getByRole('link', { name: /SUPPLIER_PRODUCT_NOT_FOUND/ })).toHaveAttribute(
      'href',
      '/docs/referencia/mensajes#supplier-product-not-found',
    );
  });

  it('ZW63 muestra el vínculo en solo lectura, sin modificar ni quitar', async () => {
    stubApi({
      ...catalogHandlers,
      'GET /api/v1/suppliers': () => page([acme]),
      'GET /api/v1/products': () => page([product()]),
      'GET /api/v1/products/7/suppliers': () =>
        jsonResponse(200, { data: [{ product_id: 7, supplier_id: 3, supplier_sku: 'A-100', lead_time_days: 5 }] }),
    });

    renderRoute('/t/ZW63');
    await fill('Proveedor', '000003');
    await fill('Artículo', 'tor-m6-30');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('textbox', { name: 'Código del proveedor' })).toHaveValue('A-100');
    expect(screen.queryByRole('button', { name: 'Modificar' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Quitar vínculo' })).not.toBeInTheDocument();
  });
});
