import { useTranslation } from 'react-i18next';
import { Navigate, Outlet, useLocation } from 'react-router';
import { problemMessage } from '@/shared/errors/problemMessage';
import { ApiError } from '@/shared/api/ApiError';
import { Button } from '@/shared/ui/Button';
import { AuthenticatedUserContext } from './authenticatedUserContext';
import { PasswordChangeScreen } from './PasswordChangeScreen';
import { loginRedirectState, redirectTargetFrom } from './redirectTarget';
import { useSession } from './session';

const sessionStatusClass = 'mx-auto mt-[10dvh] max-w-lg rounded-sm border border-border bg-surface p-4';

function SessionPending() {
  const { t } = useTranslation();

  return (
    <p role="status" className={sessionStatusClass}>
      {t('status.loadingSession')}
    </p>
  );
}

function SessionError({ error, onRetry }: { error: Error; onRetry: () => void }) {
  const { t } = useTranslation();
  const message = error instanceof ApiError ? problemMessage(error) : t('codes.UNKNOWN', { ns: 'errors' });

  return (
    <div role="alert" className={`${sessionStatusClass} grid gap-2 text-error`}>
      <p>{message}</p>
      <Button className="w-fit" onClick={onRetry}>
        {t('actions.retry')}
      </Button>
    </div>
  );
}

// Rutas que requieren sesión. Sin sesión lleva al login recordando la ruta pedida.
export function RequireAuth() {
  const session = useSession();
  const location = useLocation();

  if (session.isPending) {
    return <SessionPending />;
  }

  if (session.isError) {
    return (
      <SessionError
        error={session.error}
        onRetry={() => {
          void session.refetch();
        }}
      />
    );
  }

  if (session.data === null) {
    return <Navigate to="/login" replace state={loginRedirectState(location)} />;
  }

  // Con una contraseña inicial o reseteada solo se puede cambiarla (REG-021): en lugar de
  // la ruta pedida se muestra el cambio, que no tiene ruta propia y no se puede saltear.
  return (
    <AuthenticatedUserContext value={session.data}>
      {session.data.password_change_required ? <PasswordChangeScreen /> : <Outlet />}
    </AuthenticatedUserContext>
  );
}

// Rutas solo para quien no inició sesión (el login). Con sesión vuelve a la ruta pedida.
export function GuestOnly() {
  const session = useSession();
  const location = useLocation();

  if (session.isPending) {
    return <SessionPending />;
  }

  if (session.data) {
    return <Navigate to={redirectTargetFrom(location.state)} replace />;
  }

  // Un error al consultar la sesión no impide mostrar el login: el envío del formulario
  // informa el problema si persiste.
  return <Outlet />;
}
