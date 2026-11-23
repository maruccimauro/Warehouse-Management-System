import { useTranslation } from 'react-i18next';
import { LoginForm } from '@/features/auth/LoginForm';

export function LoginPage() {
  const { t } = useTranslation(['auth', 'common']);

  return (
    <main className="flex min-h-dvh justify-center bg-canvas px-4 pt-[10dvh]">
      {/* Solo el formulario arriba: el teclado del móvil no tapa el botón de ingreso. */}
      <section className="h-fit w-full max-w-lg rounded-sm border border-border bg-surface shadow-sm">
        <div className="flex items-baseline gap-3 rounded-t-sm bg-shell px-4 py-2 text-shell-fg">
          <span className="font-semibold tracking-wide">{t('app.name', { ns: 'common' })}</span>
          <span className="text-xs text-shell-muted">{t('app.fullName', { ns: 'common' })}</span>
        </div>
        <div className="grid gap-4 p-4">
          <div>
            <h1 className="text-lg font-semibold">{t('login.title')}</h1>
            <p className="text-ink-muted">{t('login.description')}</p>
          </div>
          <LoginForm />
        </div>
      </section>
    </main>
  );
}
