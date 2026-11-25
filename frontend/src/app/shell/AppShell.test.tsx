import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AuthenticatedUser } from '@/features/auth/authApi';
import { problemResponse, stubApi, testUser, userResponse } from '@/test/fakeApi';
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
    // La transacción se carga en diferido: el foco llega cuando su contenido está en el DOM.
    await waitFor(() => {
      expect(screen.getByLabelText('Idioma')).toHaveFocus();
    });
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

    const logistics = within(tree).getByRole('treeitem', { name: /^Logística/ });

    expect(logistics).toHaveFocus();

    // Plegada, la carpeta siguiente queda debajo.
    await userEvent.keyboard('{ArrowLeft}{ArrowDown}');

    const purchasing = within(tree).getByRole('treeitem', { name: /^Compras/ });

    expect(purchasing).toHaveFocus();

    await userEvent.keyboard('{ArrowLeft}{ArrowDown}');

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
  it('graba el depósito por defecto en el servidor con Ctrl+S', async () => {
    const api = stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })) });
    renderRoute('/t/SU3');
    const lgn = await screen.findByLabelText('LGN');

    expect(lgn).toHaveValue('');
    expect(statusBar().getByText('ninguno')).toBeInTheDocument();

    // Ctrl+S con el foco todavía en el campo graba lo escrito, normalizado a mayúsculas.
    await userEvent.type(lgn, 'nor');
    expect(lgn).toHaveAccessibleDescription(/Norte/);
    await userEvent.keyboard('{Control>}s{/Control}');

    expect(await statusBar().findByText('Los parámetros de usuario se grabaron.')).toBeInTheDocument();
    expect(statusBar().getByText('NOR')).toBeInTheDocument();
    expect(api.requestsTo('PUT', '/api/v1/me/parameters')[0]?.body).toEqual({ LGN: 'NOR' });
    // El idioma no cambió: no se envía.
    expect(api.requestsTo('PATCH', '/api/v1/me')).toHaveLength(0);
  });

  it('muestra el valor grabado en el servidor', async () => {
    stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })) }, { parameters: { LGN: 'NOR' } });
    renderRoute('/t/SU3');

    const lgn = await screen.findByLabelText('LGN');

    expect(lgn).toHaveValue('NOR');
    expect(lgn).toHaveAccessibleDescription(/^Depósito por defecto.* Norte$/);
  });

  it('descarta un depósito grabado que ya no está asignado', async () => {
    stubApi(
      { 'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses: warehouses.slice(0, 1) })) },
      { parameters: { LGN: 'NOR' } },
    );
    renderRoute('/t/SU3');

    expect(await screen.findByLabelText('LGN')).toHaveValue('');
    expect(statusBar().getByText('ninguno')).toBeInTheDocument();
  });

  it('no graba un depósito escrito que no está asignado', async () => {
    const api = stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })) });
    renderRoute('/t/SU3');
    const lgn = await screen.findByLabelText('LGN');

    await userEvent.type(lgn, 'sur{Enter}');

    expect(lgn).toHaveAttribute('aria-invalid', 'true');
    expect(await statusBar().findByRole('alert')).toHaveTextContent('El depósito SUR no existe o no está asignado al usuario.');

    await userEvent.keyboard('{Control>}s{/Control}');

    // Como el PAI de SAP: mensaje E con el campo y el foco vuelve a él (E2.22).
    expect(await statusBar().findByText('LGN: El valor no existe.')).toBeInTheDocument();
    expect(lgn).toHaveFocus();
    expect(lgn).toHaveAccessibleDescription(/El valor no existe\.$/);
    expect(api.requestsTo('PUT', '/api/v1/me/parameters')).toHaveLength(0);
  });

  it('F4 en LGN ofrece los depósitos asignados y toma el elegido', async () => {
    stubApi({ 'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })) });
    renderRoute('/t/SU3');
    const lgn = await screen.findByLabelText('LGN');

    lgn.focus();
    await userEvent.keyboard('{F4}');

    const dialog = await screen.findByRole('dialog', { name: 'Depósitos' });
    const grid = within(dialog).getByRole('grid', { name: 'Resultados: Depósitos' });

    expect(within(grid).getAllByRole('row')).toHaveLength(warehouses.length + 1);

    // Con el popup abierto, F3 no sale de la transacción de abajo.
    fireEvent.keyDown(window, { key: 'F3' });
    expect(screen.getByRole('heading', { level: 1, name: 'Parámetros de usuario' })).toBeInTheDocument();

    await userEvent.dblClick(within(grid).getByRole('gridcell', { name: 'Norte' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(lgn).toHaveValue('NOR');
    expect(lgn).toHaveFocus();
  });

  it('informa en la barra de estado una grabación rechazada', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })),
      'PUT /api/v1/me/parameters': () => problemResponse(422, 'VALIDATION_FAILED', { errors: { LGN: ['El valor no es válido.'] } }),
    });
    renderRoute('/t/SU3');

    const lgn = await screen.findByLabelText('LGN');

    await userEvent.type(lgn, 'NOR');
    await userEvent.keyboard('{Control>}s{/Control}');

    // El 422 se muestra en el campo y en la barra de estado, con el foco en el campo (E2.22).
    expect(await statusBar().findByRole('alert')).toHaveTextContent('LGN: El valor no es válido.');
    expect(lgn).toHaveAttribute('aria-invalid', 'true');
    expect(lgn).toHaveAccessibleDescription(/El valor no es válido\./);
    expect(lgn).toHaveFocus();

    // Corregir el valor quita el error del servidor.
    await userEvent.clear(lgn);
    await userEvent.type(lgn, 'CEN');
    expect(lgn).not.toHaveAttribute('aria-invalid');
  });

  it('sin depósitos asignados el parámetro queda deshabilitado', async () => {
    await renderShell();
    await runCommand('SU3');

    expect(await screen.findByLabelText('LGN')).toBeDisabled();
    expect(screen.getByLabelText('LGN')).toHaveDisplayValue('Sin depósitos asignados');
  });

  it('ofrece solo los idiomas que la interfaz tiene traducidos', async () => {
    await renderShell('/t/SU3', testUser({ locale: 'en' }));

    const language = await screen.findByLabelText('Idioma');

    expect(within(language).getAllByRole('option').map((option) => option.textContent)).toEqual(['Español']);
    expect(language).toHaveValue('es');
  });
});

describe('armazón: menús contextuales', () => {
  async function rightClick(target: HTMLElement) {
    await userEvent.pointer({ keys: '[MouseRight]', target });
  }

  function treeItem(name: string | RegExp) {
    return within(screen.getByRole('tree', { name: 'Menú de transacciones' })).getByRole('treeitem', { name });
  }

  it('el clic secundario en una transacción del árbol la agrega a favoritos', async () => {
    await renderShell();
    const su3 = treeItem('Parámetros de usuario');

    await rightClick(su3);

    const menu = screen.getByRole('menu', { name: 'Acciones del menú de transacciones' });

    expect(within(menu).getByRole('menuitem', { name: 'Ejecutar' })).toHaveFocus();

    await userEvent.click(within(menu).getByRole('menuitem', { name: 'Agregar a favoritos' }));

    expect(statusBar().getByRole('status')).toHaveTextContent('La transacción SU3 se agregó a favoritos.');
    expect(within(treeItem(/^Favoritos/)).getByRole('treeitem', { name: 'Parámetros de usuario' })).toBeInTheDocument();
    expect(su3).toHaveFocus();

    await rightClick(su3);

    expect(screen.getByRole('menuitem', { name: 'Quitar de favoritos' })).toBeInTheDocument();
  });

  it('ejecuta la transacción o la abre en una ventana nueva', async () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    await renderShell();

    await rightClick(treeItem('Parámetros de usuario'));
    await userEvent.click(screen.getByRole('menuitem', { name: 'Abrir en ventana nueva' }));

    expect(open).toHaveBeenCalledWith('/t/SU3', '_blank', 'noopener');

    await rightClick(treeItem('Parámetros de usuario'));
    await userEvent.click(screen.getByRole('menuitem', { name: 'Ejecutar' }));

    expect(heading()).toHaveTextContent('Parámetros de usuario');
  });

  it('contrae una carpeta y muestra los códigos desde el menú', async () => {
    await renderShell();

    await rightClick(treeItem(/^Sistema/));
    await userEvent.click(screen.getByRole('menuitem', { name: 'Contraer' }));

    expect(treeItem(/^Sistema/)).toHaveAttribute('aria-expanded', 'false');

    await rightClick(treeItem(/^Sistema/));
    await userEvent.click(screen.getByRole('menuitem', { name: 'Expandir todo' }));

    expect(treeItem(/^Datos de usuario/)).toHaveAttribute('aria-expanded', 'true');

    await rightClick(treeItem(/^Sistema/));
    await userEvent.keyboard('{End} ');

    expect(screen.getByRole('menuitemcheckbox', { name: 'Mostrar códigos de transacción' })).toHaveAttribute('aria-checked', 'true');
    expect(treeItem('SU3 Parámetros de usuario')).toBeInTheDocument();
    expect(screen.getByLabelText('Mostrar códigos de transacción')).toBeChecked();
  });

  it('el área de trabajo ofrece las funciones estándar con su disponibilidad', async () => {
    await renderShell('/t/SU3', testUser({ warehouses }));

    await rightClick(heading());

    const menu = screen.getByRole('menu', { name: 'Funciones de la pantalla' });

    expect(within(menu).getByRole('menuitem', { name: 'Grabar' })).toHaveAttribute('aria-keyshortcuts', 'Control+S');
    expect(within(menu).getByRole('menuitem', { name: 'Ejecutar' })).toHaveAttribute('aria-disabled', 'true');

    await userEvent.click(within(menu).getByRole('menuitem', { name: 'Atrás' }));

    expect(heading()).toHaveTextContent('Menú de acceso al sistema');

    await rightClick(heading());

    expect(screen.getByRole('menuitem', { name: 'Atrás' })).toHaveAttribute('aria-disabled', 'true');
    expect(screen.queryByRole('menuitem', { name: 'Agregar a favoritos' })).not.toBeInTheDocument();
  });

  it('los campos de la transacción conservan el menú del navegador', async () => {
    await renderShell('/t/SU3', testUser({ warehouses }));

    expect(fireEvent.contextMenu(screen.getByLabelText('LGN'))).toBe(true);
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  });
});

describe('armazón: ayuda F1', () => {
  function stubOpen() {
    return vi.spyOn(window, 'open').mockReturnValue(null);
  }

  it('F1 abre la ayuda del campo con foco en la pestaña de ayuda', async () => {
    const open = stubOpen();
    await renderShell('/t/SU3', testUser({ warehouses }));
    const lgn = await screen.findByLabelText('LGN');

    lgn.focus();

    // false: la tecla se cancela y el navegador no abre su propia ayuda.
    expect(fireEvent.keyDown(lgn, { key: 'F1' })).toBe(false);
    expect(open).toHaveBeenCalledWith('/docs/primeros-pasos/parametros-de-usuario#lgn', 'wms-help');
  });

  it('sin un campo con ayuda propia abre la página de la transacción', async () => {
    const open = stubOpen();
    await renderShell('/t/SU3', testUser({ warehouses }));

    heading().focus();
    fireEvent.keyDown(heading(), { key: 'F1' });

    expect(open).toHaveBeenCalledWith('/docs/primeros-pasos/parametros-de-usuario', 'wms-help');
  });

  it('en el menú abre el inicio de la wiki', async () => {
    const open = stubOpen();
    await renderShell();

    fireEvent.keyDown(document.body, { key: 'F1' });

    expect(open).toHaveBeenCalledWith('/docs/', 'wms-help');
  });

  it('el botón Ayuda usa el último campo enfocado del área de trabajo', async () => {
    const open = stubOpen();
    await renderShell('/t/SU3', testUser({ warehouses }));
    const help = screen.getByRole('button', { name: 'Ayuda' });

    expect(help).toHaveAttribute('aria-keyshortcuts', 'F1');

    screen.getByLabelText('Idioma').focus();
    await userEvent.click(help);

    expect(open).toHaveBeenCalledWith('/docs/primeros-pasos/parametros-de-usuario#locale', 'wms-help');
  });

  it('el menú contextual del área de trabajo ofrece la ayuda', async () => {
    const open = stubOpen();
    await renderShell('/t/SU3', testUser({ warehouses }));

    fireEvent.contextMenu(heading());
    await userEvent.click(screen.getByRole('menuitem', { name: 'Ayuda' }));

    expect(open).toHaveBeenCalledWith('/docs/primeros-pasos/parametros-de-usuario', 'wms-help');
  });

  it('el código de un error documentado enlaza a su explicación', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })),
      'PUT /api/v1/me/parameters': () => problemResponse(500, 'INTERNAL_ERROR'),
    });
    renderRoute('/t/SU3');

    await userEvent.type(await screen.findByLabelText('LGN'), 'NOR');
    await userEvent.keyboard('{Control>}s{/Control}');

    const link = await statusBar().findByRole('link', { name: 'Ayuda sobre el mensaje INTERNAL_ERROR' });

    expect(link).toHaveAttribute('href', '/docs/referencia/mensajes#internal-error');
    expect(link).toHaveAttribute('target', 'wms-help');
  });

  it('un código sin entrada en la wiki se muestra sin enlace', async () => {
    stubApi({
      'GET /api/v1/auth/me': () => userResponse(testUser({ warehouses })),
      'PUT /api/v1/me/parameters': () => problemResponse(409, 'SOMETHING_NEW'),
    });
    renderRoute('/t/SU3');

    await userEvent.type(await screen.findByLabelText('LGN'), 'NOR');
    await userEvent.keyboard('{Control>}s{/Control}');

    expect(await statusBar().findByText('(SOMETHING_NEW)')).toBeInTheDocument();
    expect(statusBar().queryByRole('link')).not.toBeInTheDocument();
  });
});
