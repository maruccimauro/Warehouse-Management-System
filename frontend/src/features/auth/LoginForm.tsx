import { useForm, useSelector } from '@tanstack/react-form';
import { useId, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@/shared/api/ApiError';
import { problemMessage } from '@/shared/errors/problemMessage';
import { useCountdown } from '@/shared/hooks/useCountdown';
import { Button } from '@/shared/ui/Button';
import { FormActions } from '@/shared/ui/form/FormActions';
import { firstInvalidField, serverFieldMessages, setServerError, visibleFieldError } from '@/shared/ui/form/formErrors';
import { FormFieldLayout } from '@/shared/ui/form/FormFieldLayout';
import { createLoginSchema, type LoginFormValues } from './loginSchema';
import { useLogin } from './session';

interface FormError {
  message: string;
  // El aviso de demasiados intentos deja de tener sentido cuando termina la espera.
  rateLimited: boolean;
}

const credentialFields = ['email', 'password'] as const;
const defaultValues: LoginFormValues = { email: '', password: '' };

// Sin texto no ocupa lugar, pero sigue en el árbol de accesibilidad para anunciar el próximo aviso.
const alertClass = 'rounded-sm border border-error/40 bg-error-soft px-3 py-2 text-error empty:border-0 empty:p-0';

export function LoginForm() {
  const { t } = useTranslation('auth');
  const schema = useMemo(() => createLoginSchema(t), [t]);
  const loginMutation = useLogin();
  const [formError, setFormError] = useState<FormError>();
  const [retrySeconds, startRetryCountdown] = useCountdown();
  const [passwordVisible, setPasswordVisible] = useState(false);
  const id = useId();
  const ids = { email: `${id}-email`, password: `${id}-password` };
  const inputId = (field: string) => `${id}-${field}`;

  const form = useForm({
    defaultValues,
    // El esquema valida en cada cambio; el error se ve al salir del campo y desde ahí en cada
    // cambio (como onTouched), o al enviar (E2.22, D2).
    validators: { onChange: schema },
    onSubmit: ({ value }) => {
      setFormError(undefined);
      // Los valores transformados por el esquema (el correo sin espacios).
      loginMutation.mutate(schema.parse(value), { onError: showError });
    },
    onSubmitInvalid: ({ formApi }) => {
      const first = firstInvalidField(formApi);

      if (first !== undefined) {
        document.getElementById(inputId(first.name))?.focus();
      }
    },
  });
  const submitted = useSelector(form.store, (state) => state.submissionAttempts > 0);

  function showError(error: Error): void {
    if (!(error instanceof ApiError)) {
      setFormError({ message: t('codes.UNKNOWN', { ns: 'errors' }), rateLimited: false });

      return;
    }

    switch (error.code) {
      case 'VALIDATION_FAILED': {
        // Los mensajes por campo vienen traducidos por el backend (SetLocale).
        const failed = serverFieldMessages(error.fieldErrors, credentialFields);

        failed.forEach(({ name, message }) => {
          setServerError(form, name, message);
        });

        if (failed[0] === undefined) {
          setFormError({ message: problemMessage(error), rateLimited: false });
        } else {
          document.getElementById(inputId(failed[0].name))?.focus();
        }

        return;
      }
      case 'INVALID_CREDENTIALS':
        // El mensaje no dice cuál de los dos datos falló; se conserva el correo y se pide
        // de nuevo la contraseña.
        setFormError({ message: problemMessage(error), rateLimited: false });
        form.setFieldValue('password', '');
        document.getElementById(ids.password)?.focus();

        return;
      case 'TOO_MANY_REQUESTS':
        if (error.retryAfterSeconds !== undefined && error.retryAfterSeconds > 0) {
          setFormError({ message: t('login.retryIn', { count: error.retryAfterSeconds }), rateLimited: true });
          startRetryCountdown(error.retryAfterSeconds);

          return;
        }

        setFormError({ message: problemMessage(error), rateLimited: false });

        return;
      default:
        setFormError({ message: problemMessage(error), rateLimited: false });
    }
  }

  const waitingRetry = retrySeconds > 0;
  const visibleError = formError?.rateLimited === true && !waitingRetry ? undefined : formError?.message;

  let submitLabel = t('login.submit');

  if (loginMutation.isPending) {
    submitLabel = t('login.submitting');
  } else if (waitingRetry) {
    submitLabel = t('login.retryButton', { count: retrySeconds });
  }

  return (
    <form
      className="grid gap-3"
      method="post"
      // Los mensajes salen de las traducciones de la interfaz, no del navegador; los
      // atributos required y type="email" se mantienen por semántica y autocompletado.
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        void form.handleSubmit();
      }}
    >
      {/* Región persistente: un aviso que cambia de texto se anuncia de forma confiable. */}
      <div role="alert" className={alertClass}>
        {visibleError}
      </div>

      <form.Field name="email">
        {(field) => {
          const error = visibleFieldError(field.state.meta, submitted);
          const errorId = `${ids.email}-error`;

          return (
            <FormFieldLayout htmlFor={ids.email} label={t('login.email')} error={error} errorId={errorId}>
              <input
                id={ids.email}
                name="email"
                className="field-input"
                type="email"
                autoComplete="username"
                spellCheck={false}
                enterKeyHint="next"
                required
                aria-invalid={error !== undefined}
                aria-describedby={error === undefined ? undefined : errorId}
                value={field.state.value}
                onChange={(event) => {
                  field.handleChange(event.target.value);
                  setServerError(form, 'email', undefined);
                }}
                onBlur={field.handleBlur}
              />
            </FormFieldLayout>
          );
        }}
      </form.Field>

      <form.Field name="password">
        {(field) => {
          const error = visibleFieldError(field.state.meta, submitted);
          const errorId = `${ids.password}-error`;

          return (
            <FormFieldLayout htmlFor={ids.password} label={t('login.password')} error={error} errorId={errorId}>
              <div className="flex gap-1">
                <input
                  id={ids.password}
                  name="password"
                  className="field-input"
                  type={passwordVisible ? 'text' : 'password'}
                  autoComplete="current-password"
                  enterKeyHint="done"
                  required
                  aria-invalid={error !== undefined}
                  aria-describedby={error === undefined ? undefined : errorId}
                  value={field.state.value}
                  onChange={(event) => {
                    field.handleChange(event.target.value);
                    setServerError(form, 'password', undefined);
                  }}
                  onBlur={field.handleBlur}
                />
                <Button
                  variant="ghost"
                  className="shrink-0 whitespace-nowrap aria-pressed:bg-selected"
                  aria-pressed={passwordVisible}
                  aria-controls={ids.password}
                  onClick={() => {
                    setPasswordVisible((visible) => !visible);
                  }}
                >
                  {t('login.showPassword')}
                </Button>
              </div>
            </FormFieldLayout>
          );
        }}
      </form.Field>

      {/* Deshabilitado solo durante el envío (evita el doble envío) y mientras dura el
          Retry-After, cuando el servidor rechazaría el intento de todos modos. */}
      <FormActions>
        <Button type="submit" variant="primary" className="w-full sm:w-auto" disabled={loginMutation.isPending || waitingRetry}>
          {submitLabel}
        </Button>
      </FormActions>
    </form>
  );
}
