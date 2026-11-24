import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Namespace } from 'i18next';
import { useState } from 'react';
import { z } from 'zod';
import { i18n } from '@/i18n';
import { ApiError } from '@/shared/api/ApiError';
import { ShellActionsProvider } from './ShellActionsProvider';
import { useShellActions } from './shellActions';
import { StatusBarProvider } from './StatusBarProvider';
import { useStatusBar } from './statusMessages';
import { UnsavedChangesContext } from './unsavedChanges';
import { useTransactionForm, type TransactionFieldControlProps } from './useTransactionForm';

// Namespace propio de la prueba, con la convención fields.<nombre> y sections.<clave>.
const testNamespace = 'transactionFormTest';
const namespace = testNamespace as Namespace;

i18n.addResourceBundle('es', testNamespace, {
  fields: { sku: 'SKU', name: 'Nombre', warehouse: 'Depósito' },
  sections: { basicData: 'Datos básicos' },
});

const schema = z.object({
  sku: z
    .string()
    .trim()
    .min(1, 'Ingrese el SKU.')
    .transform((value) => value.toUpperCase()),
  name: z.string().max(10, 'Hasta 10 caracteres.'),
  warehouse: z.string(),
});

type Values = z.input<typeof schema>;

const defaultValues: Values = { sku: '', name: '', warehouse: '' };

// Control especial con tabla de verificación: solo CEN existe.
function WarehouseInput({ id, value, onChange, onBlur, 'aria-describedby': describedBy, 'aria-invalid': invalid }: TransactionFieldControlProps) {
  return (
    <input
      id={id}
      value={value}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      onChange={(event) => {
        const next = event.target.value.toUpperCase();

        onChange(next, next === '' || next === 'CEN');
      }}
      onBlur={onBlur}
    />
  );
}

interface HarnessProps {
  onSave: (values: z.output<typeof schema>) => Promise<void>;
  readOnly?: boolean;
}

function Screen({ onSave, readOnly = false }: HarnessProps) {
  const [showName, setShowName] = useState(true);
  const { Form, Section, Field, values } = useTransactionForm({
    schema,
    defaultValues,
    namespace,
    onSave,
    readOnly,
    serverFields: { code: 'sku' },
    savedMessage: 'Grabado.',
    saveFailedMessage: 'No se grabó.',
  });

  return (
    <>
      <Form>
        <Section name="basicData">
          <Field name="sku" required description="Código del producto." />
          {showName && <Field name="name" />}
          <Field name="warehouse" as={WarehouseInput} />
        </Section>
      </Form>
      <output aria-label="SKU en edición">{values.sku}</output>
      <button
        type="button"
        onClick={() => {
          setShowName((shown) => !shown);
        }}
      >
        Alternar nombre
      </button>
    </>
  );
}

function Shell({ setDirty, ...props }: HarnessProps & { setDirty: (dirty: boolean) => void }) {
  return (
    <StatusBarProvider>
      <ShellActionsProvider>
        <UnsavedChangesContext
          value={{
            setDirty,
            confirmDiscard: (proceed) => {
              proceed();
            },
          }}
        >
          <Screen {...props} />
          <ShellControls />
        </UnsavedChangesContext>
      </ShellActionsProvider>
    </StatusBarProvider>
  );
}

// Grabar de la barra estándar (Ctrl+S) y la barra de estado.
function ShellControls() {
  const { available, run } = useShellActions();
  const { message } = useStatusBar();

  return (
    <>
      <button type="button" disabled={!available.save} onClick={() => run('save')}>
        Grabar
      </button>
      <p role="status" aria-label="Barra de estado">{message === undefined ? '' : `${message.type} ${message.text}`}</p>
    </>
  );
}

function renderForm(onSave = vi.fn(() => Promise.resolve()), readOnly = false) {
  const setDirty = vi.fn();

  render(<Shell onSave={onSave} readOnly={readOnly} setDirty={setDirty} />);

  return { onSave, setDirty };
}

function statusBar() {
  return screen.getByRole('status', { name: 'Barra de estado' });
}

async function save() {
  await userEvent.click(screen.getByRole('button', { name: 'Grabar' }));
}

describe('useTransactionForm (bloques de formulario)', () => {
  it('etiqueta por convención, sección, obligatorio y ayuda; el campo no se vuelve a montar al escribir', async () => {
    renderForm();

    expect(screen.getByRole('heading', { name: 'Datos básicos' })).toBeInTheDocument();

    const sku = screen.getByRole('textbox', { name: 'SKU' });

    expect(sku).toBeRequired();
    expect(sku).toHaveAccessibleDescription('Código del producto.');

    await userEvent.type(sku, 'abc');
    expect(screen.getByRole('textbox', { name: 'SKU' })).toBe(sku);
    expect(sku).toHaveFocus();
    expect(screen.getByLabelText('SKU en edición')).toHaveTextContent('abc');

    // Una condición de la pantalla (JSX común) no vuelve a montar los demás campos.
    await userEvent.click(screen.getByRole('button', { name: 'Alternar nombre' }));
    expect(screen.queryByRole('textbox', { name: 'Nombre' })).not.toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'SKU' })).toBe(sku);
  });

  it('el error aparece al salir del campo, no mientras se escribe por primera vez', async () => {
    renderForm();
    const name = screen.getByRole('textbox', { name: 'Nombre' });

    await userEvent.type(name, 'demasiado largo');
    expect(name).not.toHaveAttribute('aria-invalid');

    await userEvent.tab();
    expect(name).toHaveAttribute('aria-invalid', 'true');
    expect(name).toHaveAccessibleDescription('Hasta 10 caracteres.');

    // Desde ahí se revalida en cada cambio.
    await userEvent.clear(name);
    await userEvent.type(name, 'corto');
    expect(name).not.toHaveAttribute('aria-invalid');
  });

  it('grabar con errores no llama a onSave: mensaje E con el campo y foco en el primero', async () => {
    const { onSave } = renderForm();

    await save();

    expect(onSave).not.toHaveBeenCalled();
    expect(statusBar()).toHaveTextContent('E SKU: Ingrese el SKU.');
    expect(screen.getByRole('textbox', { name: 'SKU' })).toHaveFocus();
    expect(screen.getByRole('textbox', { name: 'SKU' })).toHaveAccessibleDescription('Código del producto. Ingrese el SKU.');
  });

  it('graba los valores transformados por el esquema, informa S y queda sin cambios pendientes', async () => {
    const { onSave, setDirty } = renderForm();

    await userEvent.type(screen.getByRole('textbox', { name: 'SKU' }), ' ab-1 ');
    expect(setDirty).toHaveBeenLastCalledWith(true);

    await save();

    await waitFor(() => {
      expect(statusBar()).toHaveTextContent('S Grabado.');
    });
    expect(onSave).toHaveBeenCalledWith({ sku: 'AB-1', name: '', warehouse: '' });
    expect(setDirty).toHaveBeenLastCalledWith(false);
  });

  it('un valor inexistente del control especial bloquea la grabación', async () => {
    const { onSave } = renderForm();

    await userEvent.type(screen.getByRole('textbox', { name: 'SKU' }), 'A1');
    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito' }), 'sur');
    await save();

    expect(onSave).not.toHaveBeenCalled();
    expect(statusBar()).toHaveTextContent('E Depósito: El valor no existe.');
    expect(screen.getByRole('textbox', { name: 'Depósito' })).toHaveAttribute('aria-invalid', 'true');

    await userEvent.clear(screen.getByRole('textbox', { name: 'Depósito' }));
    await userEvent.type(screen.getByRole('textbox', { name: 'Depósito' }), 'cen');
    await save();

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith({ sku: 'A1', name: '', warehouse: 'CEN' });
    });
  });

  it('los errores 422 van a cada campo (con su nombre del backend) y se quitan al corregir', async () => {
    const onSave = vi.fn(() =>
      Promise.reject(
        new ApiError({ status: 422, code: 'VALIDATION_FAILED', fieldErrors: { code: ['El SKU ya existe.'] } }),
      ),
    );

    renderForm(onSave);
    const sku = screen.getByRole('textbox', { name: 'SKU' });

    await userEvent.type(sku, 'A1');
    await save();

    await waitFor(() => {
      expect(statusBar()).toHaveTextContent('E SKU: El SKU ya existe.');
    });
    expect(sku).toHaveAttribute('aria-invalid', 'true');
    expect(sku).toHaveFocus();

    await userEvent.type(sku, '2');
    expect(sku).not.toHaveAttribute('aria-invalid');
  });

  it('un error sin campos se informa con el mensaje de falla', async () => {
    renderForm(vi.fn(() => Promise.reject(new Error('caída'))));

    await userEvent.type(screen.getByRole('textbox', { name: 'SKU' }), 'A1');
    await save();

    await waitFor(() => {
      expect(statusBar()).toHaveTextContent('E No se grabó.');
    });
  });

  it('visualizar: todo en solo lectura, sin Grabar ni aviso de cambios', () => {
    const { setDirty } = renderForm(undefined, true);

    expect(screen.getByRole('textbox', { name: 'SKU' })).toHaveAttribute('readonly');
    expect(screen.getByRole('textbox', { name: 'Depósito' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Grabar' })).toBeDisabled();
    expect(setDirty).toHaveBeenLastCalledWith(false);
  });
});
