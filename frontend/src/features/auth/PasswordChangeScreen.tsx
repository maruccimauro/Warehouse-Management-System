import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { PasswordChangeForm } from './PasswordChangeForm';
import { sessionQueryKey, useLogout } from './session';
import { useAuthenticatedUser } from './useAuthenticatedUser';

// Cambio obligatorio de una contraseña inicial o reseteada (REG-021), como el diálogo de
// clave inicial de SAP: reemplaza al armazón entero, sin ruta propia, así ningún enlace
// lo saltea. El backend responde 403 PASSWORD_CHANGE_REQUIRED a todo lo demás.
export function PasswordChangeScreen() {
  const { t } = useTranslation(['auth', 'common']);
  const user = useAuthenticatedUser();
  const queryClient = useQueryClient();
  const logoutMutation = useLogout();

  return (
    <main className="flex min-h-dvh justify-center bg-canvas px-4 pt-[10dvh]">
      <title>{t('passwordChange.documentTitle')}</title>
      <section className="h-fit w-full max-w-xl rounded-sm border border-border bg-surface shadow-sm">
        <div className="flex items-baseline gap-3 rounded-t-sm bg-shell px-4 py-2 text-shell-fg">
          <span className="font-semibold tracking-wide">{t('app.name', { ns: 'common' })}</span>
          <span className="text-xs text-shell-muted">{t('app.fullName', { ns: 'common' })}</span>
        </div>
        <div className="grid gap-4 p-4">
          <div>
            <h1 className="text-lg font-semibold">{t('passwordChange.title')}</h1>
            <p className="text-ink-muted">{t('passwordChange.requiredDescription', { name: user.name })}</p>
          </div>
          <PasswordChangeForm
            onSuccess={() => {
              // /auth/me confirma el cambio: con password_change_required en false,
              // RequireAuth muestra el armazón.
              void queryClient.invalidateQueries({ queryKey: sessionQueryKey });
            }}
            secondaryAction={{
              label: logoutMutation.isPending ? t('passwordChange.loggingOut') : t('passwordChange.logout'),
              disabled: logoutMutation.isPending,
              onClick: () => {
                logoutMutation.mutate();
              },
            }}
          />
        </div>
      </section>
    </main>
  );
}
