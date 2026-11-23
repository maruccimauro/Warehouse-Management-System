import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { ContextMenu } from './ContextMenu';
import { keepsNativeContextMenu, type ContextMenuItem } from './contextMenuItems';
import { useContextMenu } from './useContextMenu';

function Harness({ onRename, onDelete }: { onRename: () => void; onDelete: () => void }) {
  const menu = useContextMenu<string>();
  const [pinned, setPinned] = useState(false);

  const items: ContextMenuItem[] = [
    { kind: 'action', id: 'rename', label: 'Renombrar', shortcut: { display: 'F2', aria: 'F2' }, onSelect: onRename },
    { kind: 'action', id: 'copy', label: 'Copiar', disabled: true, onSelect: onRename },
    { kind: 'separator', id: 'separator' },
    { kind: 'checkbox', id: 'pin', label: 'Fijar', checked: pinned, onChange: setPinned },
    { kind: 'action', id: 'delete', label: 'Eliminar', onSelect: onDelete },
  ];

  return (
    <div>
      <button
        type="button"
        onContextMenu={(event) => {
          if (!keepsNativeContextMenu(event)) {
            menu.open(event, 'item', event.currentTarget);
          }
        }}
      >
        Elemento
      </button>
      <input
        aria-label="Nombre"
        onContextMenu={(event) => {
          if (!keepsNativeContextMenu(event)) {
            menu.open(event, 'input');
          }
        }}
      />
      <ContextMenu label="Acciones" state={menu.state} items={menu.state === null ? [] : items} onClose={menu.close} />
    </div>
  );
}

function renderHarness() {
  const onRename = vi.fn();
  const onDelete = vi.fn();

  render(<Harness onRename={onRename} onDelete={onDelete} />);

  return { onRename, onDelete, origin: screen.getByRole('button', { name: 'Elemento' }) };
}

async function openWithRightClick(target: HTMLElement) {
  await userEvent.pointer({ keys: '[MouseRight]', target });

  return screen.getByRole('menu', { name: 'Acciones' });
}

describe('ContextMenu', () => {
  it('se abre con clic secundario, enfoca el primer ítem y evita el menú del navegador', async () => {
    const { origin } = renderHarness();

    expect(screen.queryByRole('menu')).not.toBeInTheDocument();

    await openWithRightClick(origin);

    expect(screen.getByRole('menuitem', { name: 'Renombrar' })).toHaveFocus();
    expect(screen.getByRole('menuitem', { name: 'Renombrar' })).toHaveAttribute('aria-keyshortcuts', 'F2');
    expect(screen.getByRole('menuitem', { name: 'Copiar' })).toHaveAttribute('aria-disabled', 'true');
    expect(screen.getByRole('separator')).toBeInTheDocument();
  });

  it('recorre los ítems habilitados con flechas, Inicio y Fin, con vuelta al principio', async () => {
    const { origin } = renderHarness();
    await openWithRightClick(origin);

    await userEvent.keyboard('{ArrowDown}');

    // Copiar está deshabilitado y se saltea.
    expect(screen.getByRole('menuitemcheckbox', { name: 'Fijar' })).toHaveFocus();

    await userEvent.keyboard('{End}');

    expect(screen.getByRole('menuitem', { name: 'Eliminar' })).toHaveFocus();

    await userEvent.keyboard('{ArrowDown}');

    expect(screen.getByRole('menuitem', { name: 'Renombrar' })).toHaveFocus();

    await userEvent.keyboard('{ArrowUp}');

    expect(screen.getByRole('menuitem', { name: 'Eliminar' })).toHaveFocus();
  });

  it('ejecuta la acción elegida, cierra y devuelve el foco al origen', async () => {
    const { origin, onDelete } = renderHarness();
    await openWithRightClick(origin);

    await userEvent.click(screen.getByRole('menuitem', { name: 'Eliminar' }));

    expect(onDelete).toHaveBeenCalledOnce();
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    expect(origin).toHaveFocus();
  });

  it('ignora los ítems deshabilitados', async () => {
    const { origin, onRename } = renderHarness();
    await openWithRightClick(origin);

    await userEvent.click(screen.getByRole('menuitem', { name: 'Copiar' }));

    expect(onRename).not.toHaveBeenCalled();
    expect(screen.getByRole('menu')).toBeInTheDocument();
  });

  it('Escape y Tab cierran y devuelven el foco al origen', async () => {
    const { origin } = renderHarness();

    await openWithRightClick(origin);
    await userEvent.keyboard('{Escape}');

    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    expect(origin).toHaveFocus();

    await openWithRightClick(origin);
    await userEvent.tab();

    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    expect(origin).toHaveFocus();
  });

  it('un clic afuera cierra el menú', async () => {
    renderHarness();
    await openWithRightClick(screen.getByRole('button', { name: 'Elemento' }));

    await userEvent.click(document.body);

    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  });

  it('Espacio alterna la casilla sin cerrar; el clic la alterna y cierra', async () => {
    const { origin } = renderHarness();
    await openWithRightClick(origin);
    const pin = () => screen.getByRole('menuitemcheckbox', { name: 'Fijar' });

    pin().focus();
    await userEvent.keyboard(' ');

    expect(pin()).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByRole('menu')).toBeInTheDocument();

    await userEvent.click(pin());

    expect(pin()).toHaveAttribute('aria-checked', 'false');
    await waitFor(() => {
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
    expect(origin).toHaveFocus();
  });

  it('se abre desde el teclado debajo del elemento', async () => {
    const { origin } = renderHarness();
    vi.spyOn(origin, 'getBoundingClientRect').mockReturnValue(new DOMRect(40, 100, 80, 20));

    // Mayús+F10 y la tecla de menú disparan contextmenu sin coordenadas.
    fireEvent.contextMenu(origin, { clientX: 0, clientY: 0 });

    const menu = await screen.findByRole('menu');
    const popup = menu.parentElement;

    expect(popup?.style.left).toBe('40px');
    expect(popup?.style.top).toBe('120px');
  });

  it('deja el menú del navegador en los campos editables', () => {
    renderHarness();

    const notPrevented = fireEvent.contextMenu(screen.getByLabelText('Nombre'));

    expect(notPrevented).toBe(true);
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  });
});
