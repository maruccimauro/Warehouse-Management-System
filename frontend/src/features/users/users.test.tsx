import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { jsonResponse, problemResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

// Pruebas de componente de E2.14 sobre el armazón completo: SU01, ZW91 a ZW93, SUIM y PFCG.

const central = { id: 1, code: 'CENTRAL', name: 'Depósito central', address: null, is_active: true };
const north = { id: 2, code: 'NORTE', name: 'Depósito norte', address: null, is_active: true };

const admin = testUser({ username: 'ADMIN', permissions: ['users.manage', 'users.view', 'warehouses.view_all'] });
const auditor = testUser({ username: 'AUDITOR', permissions: ['users.view', 'warehouses.view_all', 'audit.view'] });

function user(overrides: Record<string, unknown> = {}) {
  return {
    id: 5,
    username: 'JPEREZ',
    name: 'Juan Pérez',
    email: 'jperez@wms.test',
    locale: 'es',
    is_active: true,
    password_change_required: false,
    roles: ['operator'],
    warehouses: [{ id: 1, code: 'CENTRAL', name: 'Depósito central', is_active: true }],
    ...overrides,
  };
}

const roles = [
  { name: 'administrator', permissions: ['users.manage', 'users.view', 'warehouses.manage'] },
  { name: 'supervisor', permissions: ['inventory.adjust', 'locations.block'] },
  { name: 'operator', permissions: ['inventory.transfer', 'warehouses.view_all'] },
  { name: 'purchasing', permissions: ['suppliers.manage'] },
  { name: 'auditor', permissions: ['audit.view', 'users.view', 'warehouses.view_all'] },
];

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

async function fillPassword(label: string, text: string) {
  // La etiqueta de un campo obligatorio termina con el asterisco.
  const field = await screen.findByLabelText(new RegExp(`^${label}`), { selector: 'input' });

  await userEvent.clear(field);
  await userEvent.type(field, text);
}

const baseHandlers = {
  'GET /api/v1/auth/me': () => userResponse(admin),
  'GET /api/v1/warehouses': () => page([central, north]),
  'GET /api/v1/roles': () => jsonResponse(200, { data: roles }),
};

beforeEach(() => {
  localStorage.clear();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('ZW91: crear usuario', () => {
  it('crea un usuario con código, contraseña inicial, roles y depósitos', async () => {
    const api = stubApi({
      ...baseHandlers,
      'POST /api/v1/users': () => jsonResponse(201, { data: user({ id: 9, username: 'APEREZ', password_change_required: true }) }),
    });

    renderRoute('/t/ZW91');
    await fill('Usuario', 'aperez');
    await fill('Nombre', 'Ana Pérez');
    await fill('Email', 'aperez@wms.test');
    await fillPassword('Contraseña inicial', 'clave-inicial-1');
    await fillPassword('Repetir contraseña', 'clave-inicial-1');
    await userEvent.click(await screen.findByRole('checkbox', { name: 'Operador' }));
    await userEvent.click(await screen.findByRole('checkbox', { name: /NORTE/ }));
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(statusBar().getByRole('status')).toHaveTextContent('Usuario APEREZ creado.');
    });
    expect(api.requestsTo('POST', '/api/v1/users')[0]?.body).toEqual({
      username: 'APEREZ',
      name: 'Ana Pérez',
      email: 'aperez@wms.test',
      password: 'clave-inicial-1',
      roles: ['operator'],
      warehouse_ids: [2],
    });
    expect(screen.getByRole('group', { name: 'Roles' })).toBeInTheDocument();
  });

  it('no graba si las contraseñas no coinciden', async () => {
    const api = stubApi(baseHandlers);

    renderRoute('/t/ZW91');
    await fill('Usuario', 'aperez');
    await fill('Nombre', 'Ana Pérez');
    await fill('Email', 'aperez@wms.test');
    await fillPassword('Contraseña inicial', 'clave-inicial-1');
    await fillPassword('Repetir contraseña', 'otra-clave-123');
    await userEvent.keyboard('{Control>}s{/Control}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('Repetir contraseña: Las contraseñas no coinciden.');
    expect(api.requestsTo('POST', '/api/v1/users')).toHaveLength(0);
  });
});

describe('SU01: gestionar usuarios', () => {
  it('informa USER_NOT_FOUND para un usuario inexistente', async () => {
    stubApi({ ...baseHandlers, 'GET /api/v1/users': () => page([]) });

    renderRoute('/t/SU01');
    await fill('Usuario', 'nadie');
    await userEvent.click(screen.getByRole('button', { name: 'Modificar' }));

    expect(await statusBar().findByRole('alert')).toHaveTextContent('El usuario NADIE no existe.');
    expect(statusBar().getByRole('link', { name: /USER_NOT_FOUND/ })).toHaveAttribute('href', '/docs/referencia/mensajes#user-not-found');
  });

  it('crear con un código existente informa VALUE_ALREADY_EXISTS', async () => {
    stubApi({ ...baseHandlers, 'GET /api/v1/users': () => page([user()]) });

    renderRoute('/t/SU01');
    await fill('Usuario', 'jperez');
    await userEvent.click(screen.getByRole('button', { name: 'Crear' }));

    expect(await statusBar().findByRole('alert')).toHaveTextContent('El usuario JPEREZ ya existe.');
  });

  it('visualiza y pasa a modificar desde la barra de aplicación', async () => {
    const api = stubApi({ ...baseHandlers, 'GET /api/v1/users': () => page([user()]) });

    renderRoute('/t/SU01');
    await fill('Usuario', 'jperez');
    await userEvent.click(screen.getByRole('button', { name: 'Visualizar' }));

    expect(await screen.findByRole('textbox', { name: 'Nombre' })).toHaveAttribute('readonly');
    expect(screen.getByRole('checkbox', { name: 'Operador' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Operador' })).toBeDisabled();

    await userEvent.click(screen.getByRole('button', { name: 'Modificar' }));

    expect(await screen.findByRole('region', { name: 'Restablecer contraseña' })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Nombre' })).not.toHaveAttribute('readonly');
    expect(api.requestsTo('GET', '/api/v1/users')[0]?.query.get('filter[username][0][low]')).toBe('JPEREZ');
  });
});

describe('ZW92: modificar usuario', () => {
  it('restablece la contraseña en su sección y la graba con la ficha', async () => {
    const api = stubApi({
      ...baseHandlers,
      'GET /api/v1/users': () => page([user()]),
      'PATCH /api/v1/users/5': () => jsonResponse(200, { data: user({ password_change_required: true }) }),
    });

    renderRoute('/t/ZW92');
    await fill('Usuario', 'jperez');
    await userEvent.keyboard('{F8}');

    const section = await screen.findByRole('region', { name: 'Restablecer contraseña' });

    expect(screen.queryByRole('button', { name: 'Asignar contraseña inicial' })).not.toBeInTheDocument();
    expect(screen.queryByText('Cambios sin grabar')).not.toBeInTheDocument();

    await userEvent.type(within(section).getByLabelText(/^Contraseña inicial/, { selector: 'input' }), 'clave-nueva-123');
    await userEvent.type(within(section).getByLabelText(/^Repetir contraseña/, { selector: 'input' }), 'clave-distinta-1');

    expect(screen.getByText('Cambios sin grabar')).toBeInTheDocument();
    expect(screen.getByText(/se cierran las sesiones abiertas del usuario/)).toBeInTheDocument();

    await userEvent.keyboard('{Control>}s{/Control}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('Repetir contraseña');
    expect(api.requestsTo('PATCH', '/api/v1/users/5')).toHaveLength(0);

    const repeat = within(section).getByLabelText(/^Repetir contraseña/, { selector: 'input' });

    await userEvent.clear(repeat);
    await userEvent.type(repeat, 'clave-nueva-123');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(api.requestsTo('PATCH', '/api/v1/users/5')[0]?.body).toEqual({
        name: 'Juan Pérez',
        email: 'jperez@wms.test',
        is_active: true,
        roles: ['operator'],
        warehouse_ids: [1],
        password: 'clave-nueva-123',
      });
    });
    // Grabar cambia de pantalla y conserva su propio mensaje.
    expect(await statusBar().findByRole('status')).toHaveTextContent('Usuario JPEREZ grabado.');
    expect(screen.queryByText('Cambios sin grabar')).not.toBeInTheDocument();
  });

  it('graba sin contraseña cuando la sección queda vacía', async () => {
    const api = stubApi({
      ...baseHandlers,
      'GET /api/v1/users': () => page([user()]),
      'PATCH /api/v1/users/5': () => jsonResponse(200, { data: user({ name: 'Juan P. Pérez' }) }),
    });

    renderRoute('/t/ZW92');
    await fill('Usuario', 'jperez');
    await userEvent.keyboard('{F8}');
    await fill('Nombre', 'Juan P. Pérez');
    await userEvent.keyboard('{Control>}s{/Control}');

    await waitFor(() => {
      expect(api.requestsTo('PATCH', '/api/v1/users/5')[0]?.body).not.toHaveProperty('password');
    });
  });

  it('borra el error de la pantalla inicial al pasar a la ficha', async () => {
    stubApi({
      ...baseHandlers,
      'GET /api/v1/users': (request) => (request.query.get('filter[username][0][low]') === 'JPEREZ' ? page([user()]) : page([])),
    });

    renderRoute('/t/ZW92');
    await fill('Usuario', 'asa21');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('ASA21');

    await fill('Usuario', 'jperez');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('region', { name: 'Restablecer contraseña' })).toBeInTheDocument();
    expect(statusBar().queryByRole('alert')).toBeEmptyDOMElement();
  });

  it('borra el error de la transacción al abrir otra desde el árbol', async () => {
    stubApi({ ...baseHandlers, 'GET /api/v1/users': () => page([]) });

    renderRoute('/t/ZW92');
    await fill('Usuario', 'asa21');
    await userEvent.keyboard('{F8}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('ASA21');

    await userEvent.click(screen.getByRole('treeitem', { name: 'Crear usuario' }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Crear usuario' })).toBeInTheDocument();
    expect(statusBar().queryByRole('alert')).toBeEmptyDOMElement();
  });

  it('muestra LAST_ADMINISTRATOR con su código en la barra de estado', async () => {
    stubApi({
      ...baseHandlers,
      'GET /api/v1/users': () => page([user({ roles: ['administrator'] })]),
      'PATCH /api/v1/users/5': () => problemResponse(409, 'LAST_ADMINISTRATOR', { detail: 'Debe quedar al menos un administrador activo.' }),
    });

    renderRoute('/t/ZW92');
    await fill('Usuario', 'jperez');
    await userEvent.keyboard('{F8}');
    await userEvent.selectOptions(await screen.findByRole('combobox', { name: 'Estado' }), 'inactive');
    await userEvent.keyboard('{Control>}s{/Control}');

    expect(await statusBar().findByRole('alert')).toHaveTextContent('Debe quedar al menos un administrador activo.');
    expect(statusBar().getByRole('link', { name: /LAST_ADMINISTRATOR/ })).toBeInTheDocument();
  });
});

describe('SUIM: lista de usuarios', () => {
  it('envía usuario como selección, rol, depósito y estado', async () => {
    const api = stubApi({ ...baseHandlers, 'GET /api/v1/auth/me': () => userResponse(auditor), 'GET /api/v1/users': () => page([user()]) });

    renderRoute('/t/SUIM');
    await fill('Usuario', 'j*');
    await fill('Rol', 'operador');
    await fill('Depósito', 'central');
    await fill('Estado', 'activo');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('gridcell', { name: 'JPEREZ' })).toBeInTheDocument();
    expect(screen.getByRole('gridcell', { name: 'Operador' })).toBeInTheDocument();

    const [first] = api.requestsTo('GET', '/api/v1/users');

    expect(first?.query.get('filter[username][0][option]')).toBe('CP');
    expect(first?.query.get('filter[username][0][low]')).toBe('J*');
    expect(first?.query.get('filter[role]')).toBe('operator');
    expect(first?.query.get('filter[warehouse_id]')).toBe('1');
    expect(first?.query.get('filter[is_active]')).toBe('1');
  });
});

describe('PFCG: visualizar rol', () => {
  it('muestra los permisos por módulo y los usuarios del rol', async () => {
    const api = stubApi({ ...baseHandlers, 'GET /api/v1/auth/me': () => userResponse(auditor), 'GET /api/v1/users': () => page([user()]) });

    renderRoute('/t/PFCG');
    await fill('Rol', 'operador');
    await userEvent.keyboard('{F8}');

    expect(await screen.findByRole('heading', { name: /Operador/ })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'warehouses.view_all' })).toHaveAttribute('href', '/docs/referencia/permisos#warehouses-view-all');
    // Un permiso de una etapa posterior todavía no tiene entrada en la referencia.
    expect(screen.getByRole('cell', { name: 'inventory.transfer' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'inventory.transfer' })).not.toBeInTheDocument();
    expect(screen.getByText('Transferir stock entre ubicaciones.')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('tab', { name: 'Usuarios' }));

    expect(await screen.findByRole('gridcell', { name: 'JPEREZ' })).toBeInTheDocument();
    expect(api.requestsTo('GET', '/api/v1/users')[0]?.query.get('filter[role]')).toBe('operator');
  });

  it('sin users.view no abre las transacciones de consulta', async () => {
    stubApi({ ...baseHandlers, 'GET /api/v1/auth/me': () => userResponse(testUser()) });

    renderRoute('/t/PFCG');

    expect(await screen.findByText(/PFCG/)).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Rol' })).not.toBeInTheDocument();
  });
});
