import { useForm, useSelector, type StandardSchemaV1 } from '@tanstack/react-form';
import type { z } from 'zod';
import type { Namespace } from 'i18next';
import { useId, useLayoutEffect, useMemo, useRef, type ComponentType, type ReactNode, type SubmitEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@/shared/api/ApiError';
import { FormFieldLayout } from '@/shared/ui/form/FormFieldLayout';
import { FormSection } from '@/shared/ui/form/FormSection';
import {
  firstInvalidField,
  serverFieldMessages,
  setServerError,
  visibleFieldError,
} from '@/shared/ui/form/formErrors';
import type { GridMessage } from '@/shared/ui/grid/gridTypes';
import { useTransactionActions } from './shellActions';
import { errorMessage, useStatusBar } from './statusMessages';
import { useUnsavedChanges } from './unsavedChanges';

// Bloques de formulario de transacción (E2.22, ADR-016), sobre TanStack Form. Cada pantalla
// se diseña en JSX, como en el Screen Painter de SAP, y lo común sale de acá: etiqueta por
// convención de traducción, control, ayuda y error, validación con el esquema Zod, grabar con
// Ctrl+S, aviso de datos sin grabar, errores 422 por campo y mensaje S o E.

// Valores del formulario: los del esquema antes de transformarse (lo que se edita).
type FormValues = Record<string, unknown>;
type FormSchema = z.ZodType<FormValues, FormValues>;

export type TransactionFieldName<TValues> = Extract<keyof TValues, string>;

// Props de un control especial (`as`): SearchHelpField y los que lo envuelven, como
// WarehouseField. valid en false marca un valor inexistente en la tabla de verificación.
export interface TransactionFieldControlProps {
  id: string;
  value: string;
  onChange: (value: string, valid: boolean) => void;
  onBlur?: (() => void) | undefined;
  onMessage: (message: GridMessage) => void;
  disabled?: boolean | undefined;
  required?: boolean | undefined;
  'aria-describedby'?: string | undefined;
  'aria-labelledby'?: string | undefined;
  'aria-invalid'?: boolean | undefined;
}

export interface TransactionFieldOption {
  value: string;
  label: string;
}

export interface TransactionFieldProps<TName extends string> {
  name: TName;
  // Control especial (F4, por ejemplo). Sin él, un <input>; con options, un <select>.
  as?: ComponentType<TransactionFieldControlProps>;
  options?: readonly TransactionFieldOption[];
  type?: 'text' | 'email' | 'password';
  inputMode?: 'text' | 'numeric' | 'decimal';
  autoComplete?: string;
  required?: boolean;
  // Ayuda persistente debajo del control, ya traducida.
  description?: string | undefined;
  // Sin etiqueta propia: el nombre lo da otro elemento (la fila de una tabla de parámetros).
  labelledBy?: string;
  // Ids de otros textos que describen el campo (la columna de descripción de esa tabla).
  describedBy?: string;
  // Ancho del control (por defecto, el de la columna).
  className?: string;
  // Solo este campo en solo lectura (el código, que no cambia después del alta, DM-19).
  readOnly?: boolean;
}

export interface TransactionFormOptions<TSchema extends FormSchema> {
  schema: TSchema;
  // Estable entre renders (useMemo): TanStack Form compara las opciones en cada render.
  defaultValues: z.input<TSchema>;
  // Namespace de traducción con fields.<nombre> y sections.<clave>.
  namespace: Namespace;
  // Recibe los valores ya transformados por el esquema y los valores editados, que pasan a
  // ser los nuevos valores por defecto al grabar bien. Si devuelve un texto, es el mensaje S
  // (por ejemplo, con la cantidad que creó); si no, savedMessage.
  onSave: (values: z.output<TSchema>) => Promise<unknown>;
  savedMessage: string;
  saveFailedMessage: string;
  // Visualizar (actividad 03 de SAP, D5): todo en solo lectura y sin Ctrl+S. Cambiarlo vuelve
  // a montar los campos, como cambiar de pantalla.
  readOnly?: boolean;
  // Campo del formulario para cada clave de errors del 422, cuando el nombre difiere.
  serverFields?: Readonly<Record<string, TransactionFieldName<z.input<TSchema>>>>;
  // Acción que envía el formulario: grabar (Ctrl+S) o ejecutar (F8), para los programas que
  // procesan lo ingresado en lugar de grabar una ficha (la generación de ubicaciones).
  submitWith?: 'save' | 'execute';
  // F3 propio: volver de la ficha a la lista o a la pantalla inicial de la transacción.
  onBack?: (() => void) | undefined;
}

export interface TransactionForm<TValues> {
  Form: ComponentType<{ children: ReactNode }>;
  // name: clave de sections.<name> en el namespace del formulario.
  Section: ComponentType<{ name: string; plain?: boolean; children: ReactNode }>;
  Field: ComponentType<TransactionFieldProps<TransactionFieldName<TValues>>>;
  // Valores en edición, para condiciones y descripciones de la pantalla.
  values: TValues;
  save: () => Promise<void>;
}

// Texto de una traducción con clave armada en tiempo de ejecución (fields.<nombre>): las
// claves tipadas de i18next no cubren plantillas.
type DynamicT = (key: string, options?: Record<string, unknown>) => string;

function toText(value: unknown): string {
  if (typeof value === 'string') {
    return value;
  }

  return typeof value === 'number' || typeof value === 'boolean' ? String(value) : '';
}

export function useTransactionForm<TSchema extends FormSchema>(
  options: TransactionFormOptions<TSchema>,
): TransactionForm<z.input<TSchema>> {
  const { schema, namespace, readOnly = false, submitWith = 'save' } = options;
  const { show } = useStatusBar();
  const { t } = useTranslation(namespace);
  const labelOf = (name: string) => (t as unknown as DynamicT)(`fields.${name}`);
  const baseId = useId();
  const latest = useRef(options);

  useLayoutEffect(() => {
    latest.current = options;
  });

  const controlId = (name: string) => `${baseId}-${name}`;

  // Adentro el formulario trabaja con un registro de valores; el tipo estricto (nombres del
  // esquema) se aplica en la API que devuelve el hook.
  const defaultValues: FormValues = options.defaultValues;
  const validator: StandardSchemaV1<FormValues, unknown> = schema;
  const form = useForm({
    defaultValues,
    // El esquema valida en cada cambio; qué error se ve lo decide visibleFieldError (D2).
    validators: { onChange: validator },
    onSubmit: async ({ value, formApi }) => {
      const { onSave, savedMessage, saveFailedMessage, serverFields } = latest.current;
      const parsed = await schema.safeParseAsync(value);

      if (!parsed.success) {
        return;
      }

      try {
        const saved = await onSave(parsed.data);
        // Lo grabado pasa a ser el valor por defecto: sin cambios pendientes.
        formApi.reset(value);
        show({ type: 'S', text: typeof saved === 'string' ? saved : savedMessage });
      } catch (error) {
        if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') {
          const fieldMap: Readonly<Record<string, string>> = serverFields ?? {};
          const failed = serverFieldMessages(error.fieldErrors, Object.keys(formApi.state.values), (key) => fieldMap[key] ?? key);

          failed.forEach(({ name, message }) => {
            setServerError(formApi, name, message);
          });

          const [first] = failed;

          if (first !== undefined) {
            show({ type: 'E', text: `${labelOf(first.name)}: ${first.message}`, id: error.code });
            document.getElementById(controlId(first.name))?.focus();

            return;
          }
        }

        show(errorMessage(error, saveFailedMessage));
      }
    },
    // Con errores, como el PAI de SAP: mensaje E con el primero y el foco en su campo.
    onSubmitInvalid: ({ formApi }) => {
      const first = firstInvalidField(formApi);

      if (first !== undefined) {
        show({ type: 'E', text: `${labelOf(first.name)}: ${first.message}`, id: 'VALIDATION_FAILED' });
        document.getElementById(controlId(first.name))?.focus();
      }
    },
  });

  const isDefaultValue = useSelector(form.store, (state) => state.isDefaultValue);
  const values = useSelector(form.store, (state) => state.values) as z.input<TSchema>;
  const saving = useRef(false);

  const save = async () => {
    // Ctrl+S repetido mientras se graba no duplica la petición.
    if (saving.current || readOnly) {
      return;
    }

    saving.current = true;

    try {
      await form.handleSubmit();
    } finally {
      saving.current = false;
    }
  };

  useUnsavedChanges(!readOnly && !isDefaultValue);
  const submit = readOnly
    ? undefined
    : () => {
        void save();
      };

  useTransactionActions({
    save: submitWith === 'save' ? submit : undefined,
    execute: submitWith === 'execute' ? submit : undefined,
    back: options.onBack,
  });

  // Creados una sola vez por formulario (y modo): si se recrearan en cada render, React
  // volvería a montar los campos y el foco se perdería al escribir.
  const components = useMemo(() => {
    function Form({ children }: { children: ReactNode }) {
      return (
        <form
          className="grid max-w-4xl gap-4"
          noValidate
          // Enter confirma el campo y no graba, como en SAP: se graba con Ctrl+S. Además
          // cancela los envíos de los formularios de los popups F4 que se abren adentro.
          onSubmit={(event: SubmitEvent<HTMLFormElement>) => {
            event.preventDefault();
          }}
        >
          {children}
        </form>
      );
    }

    function Section({ name, plain, children }: { name: string; plain?: boolean; children: ReactNode }) {
      const { t: sectionT } = useTranslation(namespace);

      return (
        <FormSection title={(sectionT as unknown as DynamicT)(`sections.${name}`)} plain={plain}>
          {children}
        </FormSection>
      );
    }

    function Field({
      name,
      as: As,
      options: choices,
      type = 'text',
      inputMode,
      autoComplete = 'off',
      required = false,
      description,
      labelledBy,
      describedBy: extraDescribedBy,
      className = '',
      readOnly: fieldReadOnly = false,
    }: TransactionFieldProps<string>) {
      const locked = readOnly || fieldReadOnly;
      const { t: fieldT, i18n } = useTranslation(namespace);
      const { show: showMessage } = useStatusBar();
      const submitted = useSelector(form.store, (state) => state.submissionAttempts > 0);
      const id = controlId(name);
      const errorId = `${id}-error`;
      const descriptionId = `${id}-description`;
      const label = (fieldT as unknown as DynamicT)(`fields.${name}`);

      return (
        <form.Field name={name}>
          {(field) => {
            const error = visibleFieldError(field.state.meta, submitted);
            const describedBy = [
              extraDescribedBy,
              description === undefined ? undefined : descriptionId,
              error === undefined ? undefined : errorId,
            ]
              .filter((part) => part !== undefined)
              .join(' ');
            const value = toText(field.state.value);

            const change = (next: string, valid = true) => {
              field.handleChange(next);
              // Un cambio deja sin efecto el error del servidor; un valor inexistente en la tabla
              // de verificación bloquea la grabación hasta corregirlo (D3 de E2.17).
              setServerError(form, name, valid ? undefined : i18n.t('invalidValue', { ns: 'form' }));
            };

            const common = {
              id,
              required,
              'aria-invalid': error !== undefined || undefined,
              'aria-describedby': describedBy === '' ? undefined : describedBy,
              'aria-labelledby': labelledBy,
            };

            let control: ReactNode;

            if (As !== undefined) {
              control = (
                <As
                  {...common}
                  aria-invalid={error !== undefined}
                  value={value}
                  disabled={locked}
                  onChange={change}
                  onBlur={field.handleBlur}
                  onMessage={showMessage}
                />
              );
            } else if (choices !== undefined) {
              control = (
                <select
                  {...common}
                  className={`field-input max-w-72 ${className}`}
                  disabled={locked}
                  value={value}
                  onChange={(event) => {
                    change(event.target.value);
                  }}
                  onBlur={field.handleBlur}
                >
                  {choices.map((choice) => (
                    <option key={choice.value} value={choice.value}>
                      {choice.label}
                    </option>
                  ))}
                </select>
              );
            } else {
              control = (
                <input
                  {...common}
                  className={`field-input ${className}`}
                  type={type}
                  inputMode={inputMode}
                  autoComplete={autoComplete}
                  readOnly={locked}
                  value={value}
                  onChange={(event) => {
                    change(event.target.value);
                  }}
                  onBlur={field.handleBlur}
                />
              );
            }

            if (labelledBy !== undefined) {
              return (
                <div className="grid gap-1" data-help-field={name}>
                  {control}
                  {error !== undefined && (
                    <p id={errorId} className="text-xs text-error">
                      {error}
                    </p>
                  )}
                </div>
              );
            }

            return (
              <FormFieldLayout
                htmlFor={id}
                label={label}
                required={required}
                description={description}
                descriptionId={descriptionId}
                error={error}
                errorId={errorId}
                helpField={name}
              >
                {control}
              </FormFieldLayout>
            );
          }}
        </form.Field>
      );
    }

    return { Form, Section, Field };
    // controlId depende solo de baseId.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form, namespace, readOnly, baseId]);

  return {
    Form: components.Form,
    Section: components.Section,
    Field: components.Field as ComponentType<TransactionFieldProps<TransactionFieldName<z.input<TSchema>>>>,
    values,
    save,
  };
}

