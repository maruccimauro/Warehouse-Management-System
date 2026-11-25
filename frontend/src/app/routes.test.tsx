import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { problemResponse, stubApi, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

const menuHeading = { level: 1, name: 'Menú de acceso al sistema' } as const;
const loginHeading = { level: 1, name: 'Iniciar sesión' } as const;

describe('rutas', () => {
  it('sin sesión, una ruta protegida lleva al login', async () => {
    stubApi();
    renderRoute('/t/SU3');

    expect(await screen.findByRole('heading', loginHeading)).toBeInTheDocument();
    expect(document.documentElement.lang).toBe('es');
  });

  it('al iniciar sesión vuelve a la ruta pedida sin volver a consultar la sesión', async () => {
    const api = stubApi({ 'POST /api/v1/auth/login': () => userResponse() });
    renderRoute('/t/SU3');

    await userEvent.type(await screen.findByLabelText('Correo electrónico'), 'operador@wms.test');
    await userEvent.type(screen.getByLabelText('Contraseña'), 'secreto');
    await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Gestionar parámetros de usuario' })).toBeInTheDocument();
    expect(within(screen.getByRole('banner')).getByText('Operador de Prueba')).toBeInTheDocument();
    expect(api.requestsTo('GET', '/api/v1/auth/me')).toHaveLength(1);
  });

  it('con sesión, el login redirige al menú de acceso', async () => {
    stubApi({ 'GET /api/v1/auth/me': () => userResponse() });
    renderRoute('/login');

    expect(await screen.findByRole('heading', menuHeading)).toBeInTheDocument();

    const navigation = screen.getByRole('navigation', { name: 'Menú' });

    expect(within(navigation).getByRole('tree', { name: 'Menú de transacciones' })).toBeInTheDocument();
  });

  it('cerrar sesión vuelve al login', async () => {
    const api = stubApi({
      'GET /api/v1/auth/me': () => userResponse(),
      'POST /api/v1/auth/logout': () => new Response(null, { status: 204 }),
    });
    renderRoute('/');

    await userEvent.click(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    expect(await screen.findByRole('heading', loginHeading)).toBeInTheDocument();
    expect(api.requestsTo('POST', '/api/v1/auth/logout')).toHaveLength(1);
  });

  it('si no se puede verificar la sesión, informa el error y permite reintentar', async () => {
    let attempts = 0;
    stubApi({
      'GET /api/v1/auth/me': () => {
        attempts += 1;

        return attempts === 1 ? problemResponse(500, 'INTERNAL_ERROR') : userResponse();
      },
    });
    renderRoute('/');

    expect(await screen.findByRole('alert')).toHaveTextContent('Ocurrió un error inesperado.');

    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByRole('heading', menuHeading)).toBeInTheDocument();
  });

  it('muestra la página no encontrada sin requerir sesión', async () => {
    stubApi();
    renderRoute('/ruta-inexistente');

    expect(screen.getByRole('heading', { level: 1, name: 'Página no encontrada' })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('link', { name: 'Volver al inicio' }));

    await waitFor(() => {
      expect(screen.getByRole('heading', loginHeading)).toBeInTheDocument();
    });
  });
});
