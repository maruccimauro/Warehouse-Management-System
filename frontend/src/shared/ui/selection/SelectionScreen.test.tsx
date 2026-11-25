import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useMemo } from 'react';
import type { GridMessage } from '../grid/gridTypes';
import { SearchHelpField } from '../searchHelp/SearchHelpField';
import type { SearchHelp } from '../searchHelp/searchHelpTypes';
import { SelectionScreen } from './SelectionScreen';
import type { SelectionField, SelectionInputProps, SelectionValues } from './selectionTypes';
import { useSelectionScreen } from './useSelectionScreen';

interface Warehouse {
  code: string;
  name: string;
}

const warehouseHelp: SearchHelp<Warehouse> = {
  title: 'Depósitos',
  columns: [
    { id: 'code', header: 'Depósito', value: (row) => row.code },
    { id: 'name', header: 'Nombre', value: (row) => row.name },
  ],
  source: {
    kind: 'local',
    rows: [
      { code: 'CEN', name: 'Central' },
      { code: 'NOR', name: 'Norte' },
    ],
  },
  criteria: ['code', 'name'],
  valueOf: (row) => row.code,
  descriptionOf: (row) => row.name,
  normalize: (text) => text.trim().toUpperCase(),
  invalidMessage: (code) => `El depósito ${code} no existe.`,
  exportName: 'depositos',
};

function WarehouseInput(props: SelectionInputProps) {
  return <SearchHelpField {...props} searchHelp={warehouseHelp} />;
}

interface HarnessProps {
  onExecute: (values: SelectionValues) => void;
  onMessage: (message: GridMessage) => void;
}

function Harness({ onExecute, onMessage }: HarnessProps) {
  const fields = useMemo(
    (): SelectionField[] => [
      { kind: 'parameter', id: 'warehouse', label: 'Depósito', required: true, as: WarehouseInput },
      { kind: 'selectOptions', id: 'source', label: 'Depósito origen', as: WarehouseInput },
      {
        kind: 'selectOptions',
        id: 'code',
        label: 'Código',
        normalize: (text) => text.trim().toUpperCase(),
      },
      { kind: 'selectOptions', id: 'rank', label: 'Nivel', type: 'integer' },
    ],
    [],
  );
  const selection = useSelectionScreen(fields);

  return (
    <>
      <SelectionScreen selection={selection} title="Criterios" onMessage={onMessage} />
      {/* La transacción registra F8 con useTransactionActions; acá lo reemplaza un botón. */}
      <button
        type="button"
        onClick={() => {
          const values = selection.validate(onMessage);

          if (values !== undefined) {
            onExecute(values);
          }
        }}
      >
        Ejecutar
      </button>
    </>
  );
}

function renderScreen() {
  const onExecute = vi.fn();
  const onMessage = vi.fn();
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  render(
    <QueryClientProvider client={client}>
      <Harness onExecute={onExecute} onMessage={onMessage} />
    </QueryClientProvider>,
  );

  return { onExecute, onMessage };
}

async function execute() {
  await userEvent.click(screen.getByRole('button', { name: 'Ejecutar' }));
}

function dialog(label: string) {
  return screen.getByRole('dialog', { name: `Selección múltiple: ${label}` });
}

describe('SelectionScreen (pantalla de selección)', () => {
  it('no ejecuta sin un obligatorio: mensaje E y el foco va al campo', async () => {
    const { onExecute, onMessage } = renderScreen();

    // jsdom no asigna todavía el rol search al elemento <search>.
    expect(screen.getByRole('heading', { name: 'Criterios' }).closest('search')).not.toBeNull();
    expect(screen.getByRole('textbox', { name: 'Depósito' })).toBeRequired();

    await execute();

    expect(onExecute).not.toHaveBeenCalled();
    expect(onMessage).toHaveBeenLastCalledWith({ type: 'E', text: 'Completar todos los campos obligatorios.', id: 'REQUIRED_FIELDS_MISSING' });
    expect(screen.getByRole('textbox', { name: 'Depósito' })).toHaveFocus();
  });

  it('un parámetro con un valor inexistente no ejecuta', async () => {
    const { onExecute, onMessage } = renderScreen();

    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito' }), 'xyz');
    await execute();

    expect(onExecute).not.toHaveBeenCalled();
    expect(onMessage).toHaveBeenLastCalledWith({ type: 'E', text: 'Depósito: el valor no existe.', id: 'VALUE_NOT_FOUND' });
  });

  it('desde y hasta arman un rango normalizado; los límites no usan la tabla de verificación', async () => {
    const { onExecute, onMessage } = renderScreen();

    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito' }), 'cen');
    // AAA no existe: en un límite es válido, como en SELECT-OPTIONS (D4).
    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito origen' }), 'aaa{Tab}');
    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito origen hasta' }), 'nor{Tab}');
    await userEvent.type(screen.getByRole('textbox', { name: 'Código' }), ' b01');
    await execute();

    expect(onMessage).not.toHaveBeenCalled();
    expect(onExecute).toHaveBeenCalledWith({
      warehouse: 'CEN',
      source: [{ sign: 'I', option: 'BT', low: 'AAA', high: 'NOR' }],
      code: [{ sign: 'I', option: 'EQ', low: 'B01' }],
      rank: [],
    });
  });

  it('F4 funciona en un límite del rango', async () => {
    renderScreen();

    screen.getByRole('textbox', { name: 'Depósito origen hasta' }).focus();
    await userEvent.keyboard('{F4}');

    const help = screen.getByRole('dialog', { name: 'Depósitos' });

    await userEvent.type(within(help).getByLabelText('Nombre'), 'nor{Enter}');

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: 'Depósito origen hasta' })).toHaveValue('NOR');
    });
  });

  it('enteros: un límite inferior mayor que el superior lleva el foco al hasta', async () => {
    const { onExecute, onMessage } = renderScreen();

    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito' }), 'CEN');
    await userEvent.type(screen.getByRole('textbox', { name: 'Nivel' }), '9');
    await userEvent.type(screen.getByRole('textbox', { name: 'Nivel hasta' }), '3');
    await execute();

    expect(onExecute).not.toHaveBeenCalled();
    expect(onMessage).toHaveBeenLastCalledWith({
      type: 'E',
      text: 'Nivel: el límite inferior es mayor que el superior.',
      id: 'RANGE_INVERTED',
    });
    expect(screen.getByRole('textbox', { name: 'Nivel hasta' })).toHaveFocus();
  });

  it('selección múltiple: valores y rangos en pestañas, exclusiones deshabilitadas, F8 copia', async () => {
    const { onExecute } = renderScreen();

    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito' }), 'CEN');
    await userEvent.click(screen.getByRole('button', { name: 'Selección múltiple: Código' }));

    const popup = dialog('Código');
    const tabs = within(popup).getAllByRole('tab');

    expect(tabs.map((tab) => tab.textContent)).toEqual([
      'Valores individuales',
      'Rangos',
      'Excluir valores individuales',
      'Excluir rangos',
    ]);
    expect(tabs[0]).toHaveAttribute('aria-selected', 'true');
    expect(tabs[2]).toBeDisabled();
    expect(tabs[3]).toBeDisabled();

    // Siempre hay una fila vacía al final: escribir en ella agrega la entrada.
    expect(within(popup).getByRole('textbox', { name: 'Valor 1' })).toHaveFocus();
    await userEvent.keyboard('a01');
    await userEvent.type(within(popup).getByRole('textbox', { name: 'Valor 2' }), 'a05');
    expect(within(popup).getByRole('textbox', { name: 'Valor 3' })).toHaveValue('');

    // La selección sigue al foco: flecha derecha pasa a Rangos.
    tabs[0]?.focus();
    await userEvent.keyboard('{ArrowRight}');
    expect(within(popup).getByRole('tab', { name: 'Rangos' })).toHaveAttribute('aria-selected', 'true');

    await userEvent.type(within(popup).getByRole('textbox', { name: 'Desde 1' }), 'b01');
    await userEvent.type(within(popup).getByRole('textbox', { name: 'Hasta 1' }), 'b99');
    expect(within(popup).getByRole('tab', { name: 'Valores individuales (2)' })).toBeInTheDocument();

    await userEvent.keyboard('{F8}');

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    // Desde muestra la primera entrada; el botón indica que hay más.
    expect(screen.getByRole('textbox', { name: 'Código' })).toHaveValue('A01');
    expect(screen.getByRole('button', { name: 'Selección múltiple: Código' })).toHaveAccessibleDescription(
      '3 entradas',
    );

    await execute();

    expect(onExecute).toHaveBeenCalledWith(
      expect.objectContaining({
        code: [
          { sign: 'I', option: 'EQ', low: 'A01' },
          { sign: 'I', option: 'EQ', low: 'A05' },
          { sign: 'I', option: 'BT', low: 'B01', high: 'B99' },
        ],
      }),
    );
  });

  it('selección múltiple: un rango sin desde no se copia y borrar una fila la quita', async () => {
    const { onMessage } = renderScreen();

    await userEvent.click(screen.getByRole('button', { name: 'Selección múltiple: Código' }));

    const popup = dialog('Código');

    await userEvent.type(within(popup).getByRole('textbox', { name: 'Valor 1' }), 'x');
    await userEvent.click(within(popup).getByRole('button', { name: 'Borrar la fila 1' }));
    expect(within(popup).getByRole('textbox', { name: 'Valor 1' })).toHaveValue('');

    await userEvent.click(within(popup).getByRole('tab', { name: 'Rangos' }));
    await userEvent.type(within(popup).getByRole('textbox', { name: 'Hasta 1' }), 'm');
    await userEvent.click(within(popup).getByRole('button', { name: 'Aplicar' }));

    expect(onMessage).toHaveBeenLastCalledWith({ type: 'E', text: 'Código: un rango necesita el valor desde.', id: 'RANGE_LOW_MISSING' });
    expect(dialog('Código')).toBeInTheDocument();

    await userEvent.click(within(popup).getByRole('button', { name: 'Cancelar' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(screen.getByRole('textbox', { name: 'Código' })).toHaveValue('');
  });

  it('selección múltiple: condición en palabras, desde y hasta bloqueados y borrar todo los libera', async () => {
    const { onExecute } = renderScreen();

    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito' }), 'cen');
    await userEvent.click(screen.getByRole('button', { name: 'Selección múltiple: Nivel' }));

    let popup = dialog('Nivel');

    await userEvent.selectOptions(within(popup).getByRole('combobox', { name: 'Condición 1' }), 'mayor que');
    await userEvent.type(within(popup).getByRole('textbox', { name: 'Valor 1' }), '3');
    await userEvent.type(within(popup).getByRole('textbox', { name: 'Valor 2' }), '9');
    await userEvent.click(within(popup).getByRole('button', { name: 'Aplicar' }));

    // Dos entradas, una con una condición que desde y hasta no muestran: quedan bloqueados.
    expect(screen.getByRole('textbox', { name: 'Nivel' })).toBeDisabled();
    expect(screen.getByRole('textbox', { name: 'Nivel hasta' })).toBeDisabled();

    await execute();
    expect(onExecute).toHaveBeenLastCalledWith(
      expect.objectContaining({
        rank: [
          { sign: 'I', option: 'GT', low: '3' },
          { sign: 'I', option: 'EQ', low: '9' },
        ],
      }),
    );

    await userEvent.click(screen.getByRole('button', { name: 'Selección múltiple: Nivel' }));
    popup = dialog('Nivel');
    await userEvent.click(within(popup).getByRole('button', { name: 'Borrar todo' }));
    await userEvent.click(within(popup).getByRole('button', { name: 'Aplicar' }));

    expect(screen.getByRole('textbox', { name: 'Nivel' })).toBeEnabled();
  });
});
