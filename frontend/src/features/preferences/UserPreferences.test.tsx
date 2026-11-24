import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AuthenticatedUser } from '@/features/auth/authApi';
import { problemResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

const warehouses = [
  { id: 10, code: 'CEN', name: 'Central' },
  { id: 20, code: 'NOR', name: 'Norte' },
];

function statusBar() {
  return within(screen.getByRole('contentinfo', { name: 'Barra de estado' }));
}

async function waitForShell() {
  await screen.findByRole('toolbar', { name: 'Barra de herramientas estándar' });
}

describe('preferencias en el servidor (E2.21)', () => {
  it('muestra los favoritos guardados en el servidor en su orden', async () => {
    stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser()) }, { favorites: ['SU3'] });
    renderRoute('/');
    await waitForShell();

    const favorites = screen.getByRole('treeitem', { name: /^Favoritos/ });

    expect(within(favorites).getByRole('treeitem', { name: 'Parámetros de usuario' })).toBeInTheDocument();
  });

  it('graba un favorito como lista completa', async () => {
    const api = stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser()) });
    renderRoute('/t/SU3');

    await userEvent.click(await screen.findByRole('button', { name: 'Favorito' }));

    await waitFor(() => {
      expect(api.requestsTo('PUT', '/api/v1/me/favorites')[0]?.body).toEqual({ favorites: ['SU3'] });
    });
    expect(api.preferences.favorites).toEqual(['SU3']);
  });

  it('si el servidor rechaza el favorito lo revierte e informa el error', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(testUser()),
      'PUT /api/v1/me/favorites': () => problemResponse(500, 'INTERNAL_ERROR'),
    });
    renderRoute('/t/SU3');
    const favorite = await screen.findByRole('button', { name: 'Favorito' });

    await userEvent.click(favorite);

    expect(await statusBar().findByRole('alert')).toHaveTextContent('Ocurrió un error inesperado.');
    await waitFor(() => {
      expect(favorite).toHaveAttribute('aria-pressed', 'false');
    });
  });

  it('graba la opción de mostrar códigos en el usuario', async () => {
    const api = stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser()) });
    renderRoute('/');
    await waitForShell();

    await userEvent.click(screen.getByLabelText('Mostrar códigos de transacción'));

    expect(screen.getByRole('treeitem', { name: 'SU3 Parámetros de usuario' })).toBeInTheDocument();
    await waitFor(() => {
      expect(api.requestsTo('PATCH', '/api/v1/me')[0]?.body).toEqual({ show_technical_names: true });
    });
  });

  it('aplica la opción de mostrar códigos guardada en el usuario', async () => {
    stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser({ show_technical_names: true })) });
    renderRoute('/');
    await waitForShell();

    expect(screen.getByLabelText('Mostrar códigos de transacción')).toBeChecked();
    expect(screen.getByRole('treeitem', { name: 'SU3 Parámetros de usuario' })).toBeInTheDocument();
  });
});

describe('migración de lo guardado en el navegador en E1 (D8)', () => {
  const user: AuthenticatedUser = testUser({ warehouses });
  const legacyKey = `wms.preferences.${user.id}`;

  function storeLegacy(value: unknown) {
    localStorage.setItem(legacyKey, JSON.stringify(value));
  }

  it('sube parámetros, favoritos y mostrar códigos si el servidor no tiene valores, y borra la clave local', async () => {
    storeLegacy({ parameters: { LGN: 'NOR' }, favorites: ['SU3', 'su3 inválido'], showTechnicalNames: true, commandHistory: ['SU3'] });
    const api = stubApi({ 'GET /api/v1/auth/me': () => userResponse(user) });
    renderRoute('/');
    await waitForShell();

    await waitFor(() => {
      expect(localStorage.getItem(legacyKey)).toBeNull();
    });
    expect(api.preferences.parameters).toEqual({ LGN: 'NOR' });
    // Los códigos con formato inválido no se suben.
    expect(api.preferences.favorites).toEqual(['SU3']);
    expect(api.requestsTo('PATCH', '/api/v1/me')[0]?.body).toEqual({ show_technical_names: true });
    // El historial del campo de comando queda en el navegador, con su clave propia.
    expect(JSON.parse(localStorage.getItem(`wms.commandHistory.${user.id}`) ?? '[]')).toEqual(['SU3']);
  });

  it('no pisa lo que el servidor ya tiene', async () => {
    storeLegacy({ parameters: { LGN: 'NOR' }, favorites: ['SU3'], showTechnicalNames: false, commandHistory: [] });
    const api = stubApi({ 'GET /api/v1/auth/me': () => userResponse(user) }, { parameters: { LGN: 'CEN' }, favorites: ['ZT01'] });
    renderRoute('/');
    await waitForShell();

    await waitFor(() => {
      expect(localStorage.getItem(legacyKey)).toBeNull();
    });
    expect(api.requestsTo('PUT', '/api/v1/me/parameters')).toHaveLength(0);
    expect(api.requestsTo('PUT', '/api/v1/me/favorites')).toHaveLength(0);
    expect(api.requestsTo('PATCH', '/api/v1/me')).toHaveLength(0);
  });

  it('no sube un depósito por defecto que ya no está asignado', async () => {
    storeLegacy({ parameters: { LGN: 'SUR' }, favorites: [], showTechnicalNames: false, commandHistory: [] });
    const api = stubApi({ 'GET /api/v1/auth/me': () => userResponse(user) });
    renderRoute('/');
    await waitForShell();

    await waitFor(() => {
      expect(localStorage.getItem(legacyKey)).toBeNull();
    });
    expect(api.requestsTo('PUT', '/api/v1/me/parameters')).toHaveLength(0);
  });

  it('si la subida falla conserva la clave local para reintentar', async () => {
    storeLegacy({ parameters: {}, favorites: ['SU3'], showTechnicalNames: false, commandHistory: [] });
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(user),
      'PUT /api/v1/me/favorites': () => problemResponse(503, 'SERVICE_UNAVAILABLE'),
    });
    renderRoute('/');
    await waitForShell();

    await waitFor(() => {
      expect(api.requestsTo('PUT', '/api/v1/me/favorites')).toHaveLength(1);
    });
    expect(localStorage.getItem(legacyKey)).not.toBeNull();
  });
});
