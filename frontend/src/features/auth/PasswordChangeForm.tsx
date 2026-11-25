import { useForm, useSelector } from '@tanstack/react-form';
import { useMutation } from '@tanstack/react-query';
import { useId, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@/shared/api/ApiError';
import { problemMessage } from '@/shared/errors/problemMessage';
import { Button } from '@/shared/ui/Button';
import { FormActions } from '@/shared/ui/form/FormActions';
import { firstInvalidField, serverFieldMessages, setServerError, visibleFieldError } from '@/shared/ui/form/formErrors';
import { FormFieldLayout } from '@/shared/ui/form/FormFieldLayout';
import { changePassword } from './authApi';
import { createPasswordChangeSchema, passwordMinLength, type PasswordChangeValues } from './passwordChangeSchema';

const fields = ['current_password', 'password', 'password_confirmation'] as const;
type Field = (typeof fields)[number];

const autoCompleteFor: Record<Field, string> = {
  current_password: 'current-password',
  password: 'new-password',
  password_confirmation: 'new-password',
};

const defaultValues: PasswordChangeValues = { current_password: '', password: '', password_confirmation: '' };

const alertClass = 'rounded-sm border border-error/40 bg-error-soft px-3 py-2 text-error empty:border-0 empty:p-0';

interface PasswordChangeFormProps {
  onSuccess: () => void;
  // Acción secundaria junto al botón de grabar: cancelar en el diálogo de SU3, cerrar
  // sesión en el cambio obligatorio.
  secondaryAction?: { label: string; onClick: () => void; disabled?: boolean };
}

// Cambio de la propia contraseña (REG-021, PUT /me/password). Lo usan el cambio
// obligatorio después del login y el diálogo voluntario de SU3.
export function PasswordChangeForm({ onSuccess, secondaryAction }: PasswordChangeFormProps) {
  const { t } = useTranslation('auth');
  const schema = useMemo(() => createPasswordChangeSchema(t), [t]);
  const mutation = useMutation({ mutationFn: changePassword });
  const [formError, setFormError] = useState<string>();
  const id = useId();
  const inputId = (field: string) => `${id}-${field}`;

  const form = useForm({
    defaultValues,
    validators: { onChange: schema },
    onSubmit: ({ value }) => {
      setFormError(undefined);
      mutation.mutate(schema.parse(value), { onSuccess, onError: showError });
    },
    onSubmitInvalid: ({ formApi }) => {
      const first = firstInvalidField(formApi);

      if (first !== undefined) {
        document.getElementById(inputId(first.name))?.focus();
      }
    },
  });
  const submitted = useSelector(form.store, (state) => state.submissionAttempts > 0);

  const labels: Record<Field, string> = {
    current_password: t('passwordChange.currentPassword'),
    password: t('passwordChange.newPassword'),
    password_confirmation: t('passwordChange.confirmPassword'),
  };

  function showError(error: Error): void {
    if (!(error instanceof ApiError)) {
      setFormError(t('codes.UNKNOWN', { ns: 'errors' }));

      return;
    }

    if (error.code === 'VALIDATION_FAILED') {
      // Los mensajes por campo vienen traducidos por el backend.
      const failed = serverFieldMessages(error.fieldErrors, fields);

      failed.forEach(({ name, message }) => {
        setServerError(form, name, message);
      });

      if (failed[0] !== undefined) {
        document.getElementById(inputId(failed[0].name))?.focus();

        return;
      }
    }

    setFormError(problemMessage(error));
  }

  return (
    <form
      className="grid gap-3"
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        void form.handleSubmit();
      }}
    >
      <div role="alert" className={alertClass}>
        {formError}
      </div>

      {fields.map((name) => (
        <form.Field key={name} name={name}>
          {(field) => {
            const fieldId = inputId(name);
            const errorId = `${fieldId}-error`;
            const hintId = `${fieldId}-hint`;
            const error = visibleFieldError(field.state.meta, submitted, field.state.value.trim() === '');
            const describedBy = [name === 'password' ? hintId : undefined, error === undefined ? undefined : errorId]
              .filter((value) => value !== undefined)
              .join(' ');

            return (
              <FormFieldLayout
                htmlFor={fieldId}
                label={labels[name]}
                {...(name === 'password'
                  ? { description: t('passwordChange.hint', { min: passwordMinLength }), descriptionId: hintId }
                  : {})}
                error={error}
                errorId={errorId}
              >
                <input
                  id={fieldId}
                  name={name}
                  className="field-input"
                  type="password"
                  autoComplete={autoCompleteFor[name]}
                  required
                  // El foco empieza en la contraseña actual en la pantalla de cambio obligatorio;
                  // el diálogo de SU3 lo vuelve a fijar después de showModal().
                  autoFocus={name === fields[0]}
                  aria-invalid={error !== undefined}
                  aria-describedby={describedBy === '' ? undefined : describedBy}
                  value={field.state.value}
                  onChange={(event) => {
                    field.handleChange(event.target.value);
                    setServerError(form, name, undefined);
                  }}
                  onBlur={field.handleBlur}
                />
              </FormFieldLayout>
            );
          }}
        </form.Field>
      ))}

      <FormActions>
        <Button type="submit" variant="primary" disabled={mutation.isPending}>
          {mutation.isPending ? t('passwordChange.submitting') : t('passwordChange.submit')}
        </Button>
        {secondaryAction !== undefined && (
          <Button disabled={secondaryAction.disabled === true || mutation.isPending} onClick={secondaryAction.onClick}>
            {secondaryAction.label}
          </Button>
        )}
      </FormActions>
    </form>
  );
}
