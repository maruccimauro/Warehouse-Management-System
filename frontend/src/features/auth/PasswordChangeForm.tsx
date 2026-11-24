import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { useEffect, useId, useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@/shared/api/ApiError';
import { problemMessage } from '@/shared/errors/problemMessage';
import { Button } from '@/shared/ui/Button';
import { changePassword } from './authApi';
import { createPasswordChangeSchema, passwordMinLength, type PasswordChangeValues } from './passwordChangeSchema';

const fields = ['current_password', 'password', 'password_confirmation'] as const;
type Field = (typeof fields)[number];

const autoCompleteFor: Record<Field, string> = {
  current_password: 'current-password',
  password: 'new-password',
  password_confirmation: 'new-password',
};

const rowClass = 'grid gap-1 sm:grid-cols-[11rem_1fr] sm:items-start sm:gap-3 sm:[&>label]:pt-1';
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
  const {
    register,
    handleSubmit,
    setError,
    setFocus,
    formState: { errors },
  } = useForm<PasswordChangeValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { current_password: '', password: '', password_confirmation: '' },
  });
  const mutation = useMutation({ mutationFn: changePassword });
  const [formError, setFormError] = useState<string>();
  const id = useId();

  useEffect(() => {
    setFocus('current_password');
  }, [setFocus]);

  const labels: Record<Field, string> = {
    current_password: t('passwordChange.currentPassword'),
    password: t('passwordChange.newPassword'),
    password_confirmation: t('passwordChange.confirmPassword'),
  };

  const showError = (error: Error) => {
    if (!(error instanceof ApiError)) {
      setFormError(t('codes.UNKNOWN', { ns: 'errors' }));

      return;
    }

    if (error.code === 'VALIDATION_FAILED') {
      // Los mensajes por campo vienen traducidos por el backend.
      const failed = fields.flatMap((field) => {
        const message = error.fieldErrors[field]?.[0];

        return message === undefined ? [] : [{ field, message }];
      });

      failed.forEach(({ field, message }, index) => {
        setError(field, { type: 'server', message }, { shouldFocus: index === 0 });
      });

      if (failed.length > 0) {
        return;
      }
    }

    setFormError(problemMessage(error));
  };

  const onSubmit = handleSubmit((values) => {
    setFormError(undefined);
    mutation.mutate(values, { onSuccess, onError: showError });
  });

  return (
    <form
      className="grid gap-3"
      noValidate
      onSubmit={(event) => {
        void onSubmit(event);
      }}
    >
      <div role="alert" className={alertClass}>
        {formError}
      </div>

      {fields.map((field) => {
        const inputId = `${id}-${field}`;
        const errorId = `${inputId}-error`;
        const hintId = `${inputId}-hint`;
        const message = errors[field]?.message;
        const describedBy = [field === 'password' ? hintId : undefined, message === undefined ? undefined : errorId]
          .filter((value) => value !== undefined)
          .join(' ');

        return (
          <div key={field} className={rowClass}>
            <label htmlFor={inputId} className="text-ink-muted">
              {labels[field]}
            </label>
            <div className="grid gap-1">
              <input
                id={inputId}
                className="field-input"
                type="password"
                autoComplete={autoCompleteFor[field]}
                required
                aria-invalid={message !== undefined}
                aria-describedby={describedBy === '' ? undefined : describedBy}
                {...register(field)}
              />
              {field === 'password' && (
                <p id={hintId} className="text-xs text-ink-muted">
                  {t('passwordChange.hint', { min: passwordMinLength })}
                </p>
              )}
              {message !== undefined && (
                <p id={errorId} className="text-xs text-error">
                  {message}
                </p>
              )}
            </div>
          </div>
        );
      })}

      <div className="flex flex-wrap gap-2 sm:ps-[calc(11rem+0.75rem)]">
        <Button type="submit" variant="primary" disabled={mutation.isPending}>
          {mutation.isPending ? t('passwordChange.submitting') : t('passwordChange.submit')}
        </Button>
        {secondaryAction !== undefined && (
          <Button disabled={secondaryAction.disabled === true || mutation.isPending} onClick={secondaryAction.onClick}>
            {secondaryAction.label}
          </Button>
        )}
      </div>
    </form>
  );
}
