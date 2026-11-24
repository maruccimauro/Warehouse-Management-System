import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as TransactionsModule from '@/app/transactions';
import type { AuthenticatedUser } from '@/features/auth/authApi';
import { stubApi, testUser, userResponse } from '@/test/fakeApi';
import { renderRoute } from '@/test/renderRoute';

// Registro con dos transacciones de prueba además de las reales: ZT01 exige un permiso y
// ZT02 falla al cargar su chunk, como pasa cuando un despliegue borra el archivo.
vi.mock('@/app/transactions', async (importOriginal) => {
  const actual = await importOriginal<typeof TransactionsModule>();
  const { lazy } = await import('react');
  const restricted = {
    code: 'ZT01',
    titleKey: 'titles.SU3' as const,
    permission: 'users.manage' as const,
    component: lazy(() => Promise.resolve({ default: () => <p>Contenido de ZT01</p> })),
  };
  const broken = {
    code: 'ZT02',
    titleKey: 'titles.SU3' as const,
    permission: null,
    component: lazy(() => Promise.reject(new Error('Failed to fetch dynamically imported module'))),
  };
  const transactions = [...actual.transactions, restricted, broken];
  const byCode = new Map(transactions.map((transaction) => [transaction.code, transaction]));

  return {
    ...actual,
    transactions,
    menuTree: [
      ...actual.menuTree,
      { kind: 'folder', id: 'restricted', labelKey: 'folders.userData', children: [{ kind: 'transaction', code: 'ZT01' }] },
    ],
    findTransaction: (code: string) => byCode.get(code.toUpperCase()),
  };
});

async function renderShell(path: string, user: AuthenticatedUser) {
  stubApi({ 'GET /api/v1/auth/me': () => userResponse(user) });
  renderRoute(path);

  await screen.findByRole('toolbar', { name: 'Barra de herramientas estándar' });
}

function tree() {
  return within(screen.getByRole('tree', { name: 'Menú de transacciones' }));
}

function statusBar() {
  return within(screen.getByRole('contentinfo', { name: 'Barra de estado' }));
}

async function runCommand(command: string) {
  await userEvent.type(screen.getByLabelText('Campo de comando'), `${command}{Enter}`);
}

describe('armazón: transacciones según permisos', () => {
  it('sin el permiso, el árbol oculta la transacción y la carpeta que queda vacía', async () => {
    await renderShell('/', testUser());

    await userEvent.click(screen.getByLabelText('Mostrar códigos de transacción'));

    expect(tree().queryByText(/ZT01/)).not.toBeInTheDocument();
    // Solo queda la carpeta real "Datos de usuario"; la de prueba, sin transacciones, no se muestra.
    expect(tree().getAllByText('Datos de usuario')).toHaveLength(1);
    expect(tree().getByText(/SU3/)).toBeInTheDocument();
  });

  it('el campo de comando distingue una transacción sin autorización de una inexistente', async () => {
    await renderShell('/', testUser());

    await runCommand('ZT01');

    expect(statusBar().getByRole('alert')).toHaveTextContent('No tiene autorización para la transacción ZT01.');
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Menú de acceso al sistema');
  });

  it('un enlace a una transacción sin autorización vuelve al menú con el error', async () => {
    await renderShell('/t/zt01', testUser());

    expect(await statusBar().findByRole('alert')).toHaveTextContent('No tiene autorización para la transacción ZT01.');
    expect(screen.queryByText('Contenido de ZT01')).not.toBeInTheDocument();
  });

  it('con el permiso, la transacción aparece en el árbol y se abre', async () => {
    await renderShell('/', testUser({ permissions: ['users.manage'] }));

    await userEvent.click(screen.getByLabelText('Mostrar códigos de transacción'));
    await userEvent.click(tree().getByText(/ZT01/));

    expect(await screen.findByText('Contenido de ZT01')).toBeInTheDocument();
  });

  it('un chunk que no carga muestra el error y ofrece recargar sin romper el armazón', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
    await renderShell('/t/ZT02', testUser());

    const main = within(screen.getByRole('main'));

    expect(await main.findByRole('alert')).toHaveTextContent('No fue posible cargar la transacción ZT02.');
    expect(screen.getByRole('button', { name: 'Recargar la página' })).toBeInTheDocument();
    expect(screen.getByLabelText('Campo de comando')).toBeInTheDocument();
  });
});
