import { zodResolver } from '@hookform/resolvers/zod';
import { useId, useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@/shared/api/ApiError';
import { problemMessage } from '@/shared/errors/problemMessage';
import { useCountdown } from '@/shared/hooks/useCountdown';
import { Button } from '@/shared/ui/Button';
import { createLoginSchema, type LoginFormValues } from './loginSchema';
import { useLogin } from './session';

interface FormError {
  message: string;
  // El aviso de demasiados intentos deja de tener sentido cuando termina la espera.
  rateLimited: boolean;
}

const credentialFields = ['email', 'password'] as const;

// Etiqueta a la izquierda del campo, como en SAP GUI; en pantallas angostas, arriba.
const rowClass = 'grid gap-1 sm:grid-cols-[9rem_1fr] sm:items-start sm:gap-3 sm:[&>label]:pt-1';
const fieldErrorClass = 'text-xs text-error';
// Sin texto no ocupa lugar, pero sigue en el árbol de accesibilidad para anunciar el próximo aviso.
const alertClass = 'rounded-sm border border-error/40 bg-error-soft px-3 py-2 text-error empty:border-0 empty:p-0';

export function LoginForm() {
  const { t } = useTranslation('auth');
  const schema = useMemo(() => createLoginSchema(t), [t]);
  const {
    register,
    handleSubmit,
    setError,
    setValue,
    setFocus,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(schema),
    // Valida al salir del campo y, una vez tocado, en cada cambio: no muestra errores
    // mientras se escribe por primera vez. Al enviar con errores enfoca el primer campo.
    mode: 'onTouched',
    defaultValues: { email: '', password: '' },
  });
  const loginMutation = useLogin();
  const [formError, setFormError] = useState<FormError>();
  const [retrySeconds, startRetryCountdown] = useCountdown();
  const [passwordVisible, setPasswordVisible] = useState(false);
  const id = useId();

  const ids = {
    email: `${id}-email`,
    emailError: `${id}-email-error`,
    password: `${id}-password`,
    passwordError: `${id}-password-error`,
  };

  function showError(error: Error): void {
    if (!(error instanceof ApiError)) {
      setFormError({ message: t('codes.UNKNOWN', { ns: 'errors' }), rateLimited: false });

      return;
    }

    switch (error.code) {
      case 'VALIDATION_FAILED': {
        // Los mensajes por campo vienen traducidos por el backend (SetLocale).
        const failedFields = credentialFields.flatMap((field) => {
          const message = error.fieldErrors[field]?.[0];

          return message === undefined ? [] : [{ field, message }];
        });

        failedFields.forEach(({ field, message }, index) => {
          setError(field, { type: 'server', message }, { shouldFocus: index === 0 });
        });

        if (failedFields.length === 0) {
          setFormError({ message: problemMessage(error), rateLimited: false });
        }

        return;
      }
      case 'INVALID_CREDENTIALS':
        // El mensaje no dice cuál de los dos datos falló; se conserva el correo y se pide
        // de nuevo la contraseña.
        setFormError({ message: problemMessage(error), rateLimited: false });
        setValue('password', '');
        setFocus('password');

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

  const onSubmit = handleSubmit((values) => {
    setFormError(undefined);
    loginMutation.mutate(values, { onError: showError });
  });

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
        void onSubmit(event);
      }}
    >
      {/* Región persistente: un aviso que cambia de texto se anuncia de forma confiable. */}
      <div role="alert" className={alertClass}>
        {visibleError}
      </div>

      <div className={rowClass}>
        <label htmlFor={ids.email} className="text-ink-muted">
          {t('login.email')}
        </label>
        <div className="grid gap-1">
          <input
            id={ids.email}
            className="field-input"
            type="email"
            autoComplete="username"
            spellCheck={false}
            enterKeyHint="next"
            required
            aria-invalid={errors.email !== undefined}
            aria-describedby={errors.email === undefined ? undefined : ids.emailError}
            {...register('email')}
          />
          {errors.email?.message !== undefined && (
            <p id={ids.emailError} className={fieldErrorClass}>
              {errors.email.message}
            </p>
          )}
        </div>
      </div>

      <div className={rowClass}>
        <label htmlFor={ids.password} className="text-ink-muted">
          {t('login.password')}
        </label>
        <div className="grid gap-1">
          <div className="flex gap-1">
            <input
              id={ids.password}
              className="field-input"
              type={passwordVisible ? 'text' : 'password'}
              autoComplete="current-password"
              enterKeyHint="done"
              required
              aria-invalid={errors.password !== undefined}
              aria-describedby={errors.password === undefined ? undefined : ids.passwordError}
              {...register('password')}
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
          {errors.password?.message !== undefined && (
            <p id={ids.passwordError} className={fieldErrorClass}>
              {errors.password.message}
            </p>
          )}
        </div>
      </div>

      {/* Deshabilitado solo durante el envío (evita el doble envío) y mientras dura el
          Retry-After, cuando el servidor rechazaría el intento de todos modos. */}
      <div className="sm:ps-[calc(9rem+0.75rem)]">
        <Button type="submit" variant="primary" className="w-full sm:w-auto" disabled={loginMutation.isPending || waitingRetry}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
