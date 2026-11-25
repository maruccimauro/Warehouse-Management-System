import { fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

const warehouses = [
  { id: 10, code: 'CEN', name: 'Central' },
  { id: 20, code: 'NOR', name: 'Norte' },
];

// SU3 tiene cambios sin grabar en cuanto el valor de LGN difiere del grabado.
async function renderSu3WithChanges() {
  const api = stubApi({
    'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })),
    'POST /api/v1/auth/logout': () => new Response(null, { status: 204 }),
  });
  renderRoute('/t/SU3');

  await userEvent.type(await screen.findByLabelText('LGN'), 'NOR');

  return api;
}

function heading() {
  return screen.getByRole('heading', { level: 1, name: /Gestionar parámetros de usuario|Menú de acceso al sistema/ });
}

function confirmation() {
  return screen.queryByRole('dialog', { name: 'Salir sin grabar' });
}

describe('armazón: aviso de datos sin grabar', () => {
  it('F3 con cambios pide confirmación; seguir editando conserva la pantalla y sus datos', async () => {
    await renderSu3WithChanges();

    fireEvent.keyDown(window, { key: 'F3' });

    const dialog = await screen.findByRole('dialog', { name: 'Salir sin grabar' });
    expect(dialog).toHaveAccessibleDescription('Los datos que no se grabaron se perderán. ¿Desea salir de todos modos?');
    expect(screen.getByRole('button', { name: 'Seguir editando' })).toHaveFocus();

    await userEvent.click(screen.getByRole('button', { name: 'Seguir editando' }));

    await waitFor(() => {
      expect(confirmation()).not.toBeInTheDocument();
    });
    expect(heading()).toHaveTextContent('Gestionar parámetros de usuario');
    expect(screen.getByLabelText('LGN')).toHaveValue('NOR');
  });

  it('salir sin grabar descarta los cambios y vuelve al menú', async () => {
    await renderSu3WithChanges();

    fireEvent.keyDown(window, { key: 'F3', shiftKey: true });
    await userEvent.click(await screen.findByRole('button', { name: 'Salir sin grabar' }));

    await waitFor(() => {
      expect(heading()).toHaveTextContent('Menú de acceso al sistema');
    });
  });

  it('el campo de comando también pasa por la confirmación', async () => {
    await renderSu3WithChanges();

    await userEvent.type(screen.getByLabelText('Campo de comando'), '/n{Enter}');

    expect(await screen.findByRole('dialog', { name: 'Salir sin grabar' })).toBeInTheDocument();
  });

  it('después de grabar sale sin preguntar', async () => {
    await renderSu3WithChanges();

    fireEvent.keyDown(window, { key: 's', ctrlKey: true });
    await screen.findByText('Los parámetros de usuario se grabaron.');
    fireEvent.keyDown(window, { key: 'F3' });

    await waitFor(() => {
      expect(heading()).toHaveTextContent('Menú de acceso al sistema');
    });
    expect(confirmation()).not.toBeInTheDocument();
  });

  it('cerrar sesión con cambios pide confirmación antes de llamar a la API', async () => {
    const api = await renderSu3WithChanges();

    await userEvent.click(screen.getByRole('button', { name: 'Cerrar sesión' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Seguir editando' }));

    expect(api.requestsTo('POST', '/api/v1/auth/logout')).toHaveLength(0);

    await userEvent.click(screen.getByRole('button', { name: 'Cerrar sesión' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Salir sin grabar' }));

    await waitFor(() => {
      expect(api.requestsTo('POST', '/api/v1/auth/logout')).toHaveLength(1);
    });
  });

  it('con cambios, cerrar o recargar la pestaña activa el aviso del navegador', async () => {
    await renderSu3WithChanges();

    const event = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(true);
  });
});
