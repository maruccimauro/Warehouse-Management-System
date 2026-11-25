import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { SearchHelpField } from './SearchHelpField';
import type { SearchHelp } from './searchHelpTypes';

interface Warehouse {
  code: string;
  name: string;
}

const rows: Warehouse[] = [
  { code: 'CEN', name: 'Central' },
  { code: 'NOR', name: 'Norte' },
  { code: 'SUR', name: 'Sur' },
];

const searchHelp: SearchHelp<Warehouse> = {
  title: 'Depósitos',
  columns: [
    { id: 'code', header: 'Depósito', value: (row) => row.code },
    { id: 'name', header: 'Nombre', value: (row) => row.name },
  ],
  source: { kind: 'local', rows },
  criteria: ['code', 'name'],
  valueOf: (row) => row.code,
  descriptionOf: (row) => row.name,
  normalize: (text) => text.trim().toUpperCase(),
  invalidMessage: (code) => `El depósito ${code} no existe.`,
  exportName: 'depositos',
};

interface HarnessProps {
  initial: string;
  onChange: (value: string, valid: boolean) => void;
  onMessage: () => void;
}

function Harness({ initial, onChange, onMessage }: HarnessProps) {
  const [value, setValue] = useState(initial);

  return (
    <>
      <label htmlFor="warehouse">Depósito</label>
      <SearchHelpField
        id="warehouse"
        value={value}
        onChange={(next, valid) => {
          setValue(next);
          onChange(next, valid);
        }}
        searchHelp={searchHelp}
        onMessage={onMessage}
      />
      <button type="button">Otro control</button>
    </>
  );
}

function renderField(initial = '') {
  const onMessage = vi.fn();
  const onChange = vi.fn();
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  render(
    <QueryClientProvider client={client}>
      <Harness initial={initial} onChange={onChange} onMessage={onMessage} />
    </QueryClientProvider>,
  );

  return { onChange, onMessage, field: screen.getByLabelText('Depósito') };
}

function dialog() {
  return screen.getByRole('dialog', { name: 'Depósitos' });
}

function resultCodes(): string[] {
  const grid = within(dialog()).getByRole('grid', { name: 'Resultados: Depósitos' });
  const [, ...bodyRows] = within(grid).getAllByRole('row');

  return bodyRows.map((row) => within(row).getAllByRole('gridcell')[0]?.textContent ?? '');
}

async function pressF4(field: HTMLElement) {
  field.focus();
  await userEvent.keyboard('{F4}');
}

describe('SearchHelpField (F4)', () => {
  it('F4 abre la ayuda con todos los valores y Enter en una fila toma el valor', async () => {
    const { field, onChange } = renderField();

    await pressF4(field);

    expect(resultCodes()).toEqual(['CEN', 'NOR', 'SUR']);
    expect(within(dialog()).getByLabelText('Depósito')).toHaveFocus();

    await userEvent.keyboard('{Enter}');
    expect(document.activeElement).toBe(within(dialog()).getByRole('gridcell', { name: 'CEN' }));
    await userEvent.keyboard('{ArrowDown}{Enter}');

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(field).toHaveValue('NOR');
    expect(field).toHaveFocus();
    expect(field).toHaveAccessibleDescription('Norte');
    expect(onChange).toHaveBeenLastCalledWith('NOR', true);
  });

  it('las restricciones filtran en vivo y Enter con un único resultado lo toma', async () => {
    const { field } = renderField();

    await pressF4(field);
    await userEvent.type(within(dialog()).getByLabelText('Nombre'), 'nor');

    expect(resultCodes()).toEqual(['NOR']);
    expect(within(dialog()).getByText('1 resultado')).toBeInTheDocument();

    await userEvent.keyboard('{Enter}');

    expect(field).toHaveValue('NOR');
  });

  it('lo escrito que no es un valor válido restringe el primer criterio', async () => {
    const { field, onMessage } = renderField();

    await userEvent.type(field, 'e');
    await userEvent.click(screen.getByRole('button', { name: 'Ayuda de búsqueda: Depósitos (F4)' }));

    // Pasar al botón F4 no valida lo escrito.
    expect(onMessage).not.toHaveBeenCalled();
    expect(within(dialog()).getByLabelText('Depósito')).toHaveValue('e');
    expect(resultCodes()).toEqual(['CEN']);
  });

  it('con un valor válido abre sin restricción y con el foco en su fila', async () => {
    const { field } = renderField('SUR');

    await pressF4(field);

    expect(within(dialog()).getByLabelText('Depósito')).toHaveValue('');
    expect(document.activeElement).toBe(within(dialog()).getByRole('gridcell', { name: 'SUR' }));
  });

  it('Esc cierra sin cambiar el valor y el foco vuelve al campo', async () => {
    const { field, onChange } = renderField('CEN');

    await pressF4(field);
    await userEvent.keyboard('{Escape}');
    // jsdom no cierra el <dialog> con Esc: se simula el evento close que envía el navegador.
    dialog().dispatchEvent(new Event('close'));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(field).toHaveValue('CEN');
    expect(field).toHaveFocus();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('valida lo escrito como tabla de verificación: normaliza, informa en cada cambio y marca el error al confirmar', async () => {
    const { field, onChange, onMessage } = renderField();

    await userEvent.type(field, 'sur');

    expect(onChange).toHaveBeenLastCalledWith('SUR', true);
    expect(field).toHaveAccessibleDescription('Sur');

    await userEvent.clear(field);
    await userEvent.type(field, 'oes');

    expect(onChange).toHaveBeenLastCalledWith('OES', false);
    expect(field).not.toHaveAttribute('aria-invalid');

    await userEvent.tab();

    expect(field).toHaveValue('OES');
    expect(field).toHaveAttribute('aria-invalid', 'true');
    expect(onMessage).toHaveBeenCalledExactlyOnceWith({ type: 'E', text: 'El depósito OES no existe.', id: 'VALUE_NOT_FOUND' });

    // Volver a salir del campo sin cambios no repite el mensaje.
    field.focus();
    await userEvent.tab();

    expect(onMessage).toHaveBeenCalledTimes(1);
  });
});
