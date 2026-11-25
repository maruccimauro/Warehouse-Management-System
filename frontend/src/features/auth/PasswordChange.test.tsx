import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AuthenticatedUser } from '@/features/auth/authApi';
import { problemResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

const newPassword = 'clave-nueva-segura';

async function fillPasswordForm(current = 'clave-inicial-1', next = newPassword, confirmation = next) {
  await userEvent.type(screen.getByLabelText('Contraseña actual'), current);
  await userEvent.type(screen.getByLabelText('Contraseña nueva'), next);
  await userEvent.type(screen.getByLabelText('Repetir contraseña nueva'), confirmation);
  await userEvent.click(screen.getByRole('button', { name: 'Cambiar contraseña' }));
}

// El usuario de /auth/me cambia cuando el servidor acepta la contraseña nueva.
function stubSession(initial: AuthenticatedUser, handlers: Parameters<typeof stubApi>[0] = {}) {
  let user = initial;

  return stubApi({
    'GET /api/v1/auth/me': () => userResponse(user),
    'PUT /api/v1/me/password': () => {
      user = { ...user, password_change_required: false };

      return new Response(null, { status: 204 });
    },
    ...handlers,
  });
}

describe('cambio de contraseña obligatorio (REG-021)', () => {
  it('reemplaza al armazón aunque se pida una transacción y deja entrar después del cambio', async () => {
    const api = stubSession(testUser({ password_change_required: true }));
    renderRoute('/t/SU3');

    expect(await screen.findByRole('heading', { level: 1, name: 'Cambiar contraseña' })).toBeInTheDocument();
    expect(screen.queryByRole('tree')).not.toBeInTheDocument();
    expect(screen.getByLabelText('Contraseña actual')).toHaveFocus();
    // Sin cambio no se piden las preferencias: el backend las rechazaría con 403.
    expect(api.requestsTo('GET', '/api/v1/me/parameters')).toHaveLength(0);

    await fillPasswordForm();

    expect(api.requestsTo('PUT', '/api/v1/me/password')[0]?.body).toEqual({
      current_password: 'clave-inicial-1',
      password: newPassword,
      password_confirmation: newPassword,
    });
    expect(await screen.findByRole('heading', { level: 1, name: 'Gestionar parámetros de usuario' })).toBeInTheDocument();
  });

  it('valida en el cliente la confirmación y que la nueva sea distinta', async () => {
    const api = stubSession(testUser({ password_change_required: true }));
    renderRoute('/');
    await screen.findByLabelText('Contraseña actual');

    await fillPasswordForm('misma-clave-segura', 'misma-clave-segura', 'otra-clave-segura');

    expect(await screen.findByText('La contraseña nueva debe ser distinta de la actual.')).toBeInTheDocument();
    expect(screen.getByText('Las dos contraseñas nuevas no coinciden.')).toBeInTheDocument();
    expect(api.requestsTo('PUT', '/api/v1/me/password')).toHaveLength(0);
  });

  it('muestra en su campo el error del servidor por contraseña actual incorrecta', async () => {
    stubSession(testUser({ password_change_required: true }), {
      'PUT /api/v1/me/password': () =>
        problemResponse(422, 'VALIDATION_FAILED', { errors: { current_password: ['La contraseña actual no es correcta.'] } }),
    });
    renderRoute('/');
    await screen.findByLabelText('Contraseña actual');

    await fillPasswordForm();

    expect(await screen.findByText('La contraseña actual no es correcta.')).toBeInTheDocument();
    expect(screen.getByLabelText('Contraseña actual')).toHaveAttribute('aria-invalid', 'true');
  });

  it('permite cerrar sesión sin cambiarla', async () => {
    stubSession(testUser({ password_change_required: true }), {
      'POST /api/v1/auth/logout': () => new Response(null, { status: 204 }),
    });
    renderRoute('/');

    await userEvent.click(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument();
  });

  it('un 403 PASSWORD_CHANGE_REQUIRED a mitad de la sesión vuelve al cambio', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(testUser()),
      'PUT /api/v1/me/favorites': () => problemResponse(403, 'PASSWORD_CHANGE_REQUIRED'),
    });
    renderRoute('/t/SU3');

    await userEvent.click(await screen.findByRole('button', { name: 'Favorito' }));

    // El árbol desaparece: es la pantalla obligatoria y no el diálogo voluntario de SU3.
    await waitFor(() => {
      expect(screen.queryByRole('tree')).not.toBeInTheDocument();
    });
    expect(screen.getByLabelText('Contraseña actual')).toBeInTheDocument();
  });
});

describe('cambio de contraseña voluntario desde SU3', () => {
  it('se abre en un diálogo, cambia la contraseña e informa el resultado', async () => {
    const api = stubSession(testUser());
    renderRoute('/t/SU3');

    await userEvent.click(await screen.findByRole('button', { name: 'Cambiar contraseña' }));

    const dialog = screen.getByRole('dialog', { name: 'Cambiar contraseña' });
    expect(within(dialog).getByLabelText('Contraseña actual')).toHaveFocus();

    await userEvent.type(within(dialog).getByLabelText('Contraseña actual'), 'clave-actual-segura');
    await userEvent.type(within(dialog).getByLabelText('Contraseña nueva'), newPassword);
    await userEvent.type(within(dialog).getByLabelText('Repetir contraseña nueva'), newPassword);
    await userEvent.click(within(dialog).getByRole('button', { name: 'Cambiar contraseña' }));

    expect(await screen.findByText('La contraseña se cambió. Las demás sesiones abiertas se cerraron.')).toBeInTheDocument();
    expect(api.requestsTo('PUT', '/api/v1/me/password')).toHaveLength(1);
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Cambiar contraseña' })).not.toBeInTheDocument();
    });
  });

  it('cancelar cierra el diálogo sin llamar a la API', async () => {
    const api = stubSession(testUser());
    renderRoute('/t/SU3');

    await userEvent.click(await screen.findByRole('button', { name: 'Cambiar contraseña' }));
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(api.requestsTo('PUT', '/api/v1/me/password')).toHaveLength(0);
  });
});
