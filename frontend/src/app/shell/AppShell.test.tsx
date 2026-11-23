import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AuthenticatedUser } from '@/features/auth/authApi';
import { stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

const warehouses = [
  { id: 10, code: 'CEN', name: 'Central' },
  { id: 20, code: 'NOR', name: 'Norte' },
];

async function renderShell(path = '/', user: AuthenticatedUser = testUser()) {
  stubApi({ 'GET /api/v1/auth/me': () => userResponse(user) });
  const view = renderRoute(path);

  await screen.findByRole('toolbar', { name: 'Barra de herramientas estándar' });

  return view;
}

function heading() {
  return screen.getByRole('heading', { level: 1 });
}

async function runCommand(command: string) {
  const field = screen.getByLabelText('Campo de comando');

  await userEvent.clear(field);
  await userEvent.type(field, `${command}{Enter}`);
}

function statusBar() {
  return within(screen.getByRole('contentinfo', { name: 'Barra de estado' }));
}

describe('armazón: campo de comando', () => {
  it('abre una transacción por su código y pone el foco en su primer campo', async () => {
    await renderShell('/', testUser({ warehouses }));

    await runCommand('su3');

    expect(heading()).toHaveTextContent('Parámetros de usuario');
    expect(screen.getByLabelText('LGN')).toHaveFocus();
    expect(statusBar().getByText('SU3')).toBeInTheDocument();
    expect(document.title).toBe('SU3 Parámetros de usuario · WMS');
    expect(screen.getByLabelText('Campo de comando')).toHaveValue('');
  });

  it('informa en la barra de estado una transacción inexistente', async () => {
    await renderShell();

    await runCommand('MIGO');

    expect(statusBar().getByRole('alert')).toHaveTextContent('La transacción MIGO no existe.');
    expect(heading()).toHaveTextContent('Menú de acceso al sistema');
  });

  it('rechaza un comando con formato inválido', async () => {
    await renderShell();

    await runCommand('/x');

    expect(statusBar().getByRole('alert')).toHaveTextContent('El comando /x no es válido.');
  });

  it('/n sale de la transacción y vuelve al menú', async () => {
    await renderShell('/t/SU3');

    await runCommand('/n');

    expect(heading()).toHaveTextContent('Menú de acceso al sistema');
  });

  it('/o abre la transacción en una ventana nueva sin referencia a esta', async () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    await renderShell();

    await runCommand('/oSU3');

    expect(open).toHaveBeenCalledWith('/t/SU3', '_blank', 'noopener');
    expect(heading()).toHaveTextContent('Menú de acceso al sistema');
  });

  it('un enlace a una transacción inexistente vuelve al menú con el error', async () => {
    await renderShell('/t/XYZ1');

    await waitFor(() => {
      expect(statusBar().getByRole('alert')).toHaveTextContent('La transacción XYZ1 no existe.');
    });
    expect(heading()).toHaveTextContent('Menú de acceso al sistema');
  });

  it('sugiere primero las transacciones usadas', async () => {
    await renderShell();

    await runCommand('SU3');

    const options = Array.from(document.querySelectorAll('datalist option')).map((option) => option.getAttribute('value'));

    expect(options[0]).toBe('SU3');
  });
});

describe('armazón: teclas y barra de herramientas', () => {
  it('F3 y Mayús+F3 vuelven al menú desde una transacción', async () => {
    await renderShell('/t/SU3');

    await userEvent.keyboard('{F3}');

    expect(heading()).toHaveTextContent('Menú de acceso al sistema');

    await runCommand('SU3');
    await userEvent.keyboard('{Shift>}{F3}{/Shift}');

    expect(heading()).toHaveTextContent('Menú de acceso al sistema');
  });

  it('en el menú Atrás y Grabar no están disponibles', async () => {
    await renderShell();

    expect(screen.getByRole('button', { name: 'Atrás' })).toHaveAttribute('aria-disabled', 'true');
    expect(screen.getByRole('button', { name: 'Grabar' })).toHaveAttribute('aria-disabled', 'true');
    expect(screen.getByRole('button', { name: 'Atrás' })).toHaveAttribute('aria-keyshortcuts', 'F3');
  });

  it('la barra de herramientas es una sola parada de tabulación y se recorre con flechas', async () => {
    await renderShell('/t/SU3');
    const toolbar = screen.getByRole('toolbar', { name: 'Barra de herramientas estándar' });
    const [first, second] = within(toolbar).getAllByRole('button');

    expect(first).toHaveAttribute('tabindex', '0');
    expect(second).toHaveAttribute('tabindex', '-1');

    first?.focus();
    await userEvent.keyboard('{ArrowRight}');

    expect(second).toHaveFocus();

    await userEvent.keyboard('{End}{ArrowRight}');

    expect(first).toHaveFocus();
  });

  it('agrega y quita la transacción de favoritos', async () => {
    await renderShell('/t/SU3');
    const favorite = screen.getByRole('button', { name: 'Favorito' });

    await userEvent.click(favorite);

    expect(favorite).toHaveAttribute('aria-pressed', 'true');
    expect(statusBar().getByRole('status')).toHaveTextContent('La transacción SU3 se agregó a favoritos.');

    const favorites = screen.getByRole('treeitem', { name: /^Favoritos/ });

    expect(within(favorites).getByRole('treeitem', { name: 'Parámetros de usuario' })).toBeInTheDocument();

    await userEvent.click(favorite);

    expect(favorite).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('treeitem', { name: 'Favoritos (Sin favoritos)' })).toBeInTheDocument();
  });
});

describe('armazón: árbol de transacciones', () => {
  it('se recorre con el teclado y abre la transacción con Enter', async () => {
    await renderShell();
    const tree = screen.getByRole('tree', { name: 'Menú de transacciones' });
    const favorites = within(tree).getByRole('treeitem', { name: /^Favoritos/ });

    expect(favorites).toHaveAttribute('tabindex', '0');

    favorites.focus();
    await userEvent.keyboard('{ArrowDown}');

    const system = within(tree).getByRole('treeitem', { name: /^Sistema/ });

    expect(system).toHaveFocus();
    expect(system).toHaveAttribute('aria-expanded', 'true');

    await userEvent.keyboard('{ArrowLeft}');

    expect(system).toHaveAttribute('aria-expanded', 'false');
    expect(within(tree).queryByRole('treeitem', { name: 'Parámetros de usuario' })).not.toBeInTheDocument();

    await userEvent.keyboard('{ArrowRight}{ArrowRight}{ArrowRight}');

    expect(within(tree).getByRole('treeitem', { name: /^Datos de usuario/ })).toHaveAttribute('aria-expanded', 'true');

    await userEvent.keyboard('{End}');

    const su3 = within(tree).getByRole('treeitem', { name: 'Parámetros de usuario' });

    expect(su3).toHaveFocus();

    await userEvent.keyboard('{ArrowLeft}');

    expect(within(tree).getByRole('treeitem', { name: /^Datos de usuario/ })).toHaveFocus();

    await userEvent.keyboard('{ArrowDown}{Enter}');

    expect(heading()).toHaveTextContent('Parámetros de usuario');
    expect(within(tree).getByRole('treeitem', { name: 'Parámetros de usuario' })).toHaveAttribute('aria-current', 'page');
  });

  it('muestra los códigos de transacción cuando se pide', async () => {
    await renderShell();

    await userEvent.click(screen.getByLabelText('Mostrar códigos de transacción'));

    expect(screen.getByRole('treeitem', { name: 'SU3 Parámetros de usuario' })).toBeInTheDocument();
  });
});

describe('SU3: parámetros de usuario', () => {
  it('graba el depósito por defecto con Ctrl+S y lo recuerda', async () => {
    const { unmount } = await renderShell('/t/SU3', testUser({ warehouses }));
    const lgn = screen.getByLabelText('LGN');

    expect(lgn).toHaveDisplayValue('Sin valor');
    expect(statusBar().getByText('ninguno')).toBeInTheDocument();

    await userEvent.selectOptions(lgn, 'NOR: Norte');
    await userEvent.keyboard('{Control>}s{/Control}');

    expect(statusBar().getByRole('status')).toHaveTextContent('Los parámetros de usuario se grabaron.');
    expect(statusBar().getByText('NOR')).toBeInTheDocument();

    unmount();
    await renderShell('/t/SU3', testUser({ warehouses }));

    expect(screen.getByLabelText('LGN')).toHaveDisplayValue('NOR: Norte');
  });

  it('descarta un depósito guardado que ya no está asignado', async () => {
    const { unmount } = await renderShell('/t/SU3', testUser({ warehouses }));

    await userEvent.selectOptions(screen.getByLabelText('LGN'), 'NOR: Norte');
    await userEvent.click(screen.getByRole('button', { name: 'Grabar' }));
    unmount();

    await renderShell('/t/SU3', testUser({ warehouses: warehouses.slice(0, 1) }));

    expect(screen.getByLabelText('LGN')).toHaveDisplayValue('Sin valor');
    expect(statusBar().getByText('ninguno')).toBeInTheDocument();
  });

  it('sin depósitos asignados el parámetro queda deshabilitado y el foco va al título', async () => {
    await renderShell();
    await runCommand('SU3');

    expect(screen.getByLabelText('LGN')).toBeDisabled();
    expect(screen.getByLabelText('LGN')).toHaveDisplayValue('Sin depósitos asignados');
    expect(heading()).toHaveFocus();
  });
});
