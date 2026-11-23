import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

export function NotFoundPage() {
  const { t } = useTranslation(['notFound', 'common']);

  return (
    <main className="flex min-h-dvh justify-center bg-canvas px-4 pt-[10dvh]">
      <div className="grid h-fit max-w-lg gap-2 rounded-sm border border-border bg-surface p-4">
        <h1 className="text-lg font-semibold">{t('title')}</h1>
        <p className="text-ink-muted">{t('description')}</p>
        <Link to="/" className="text-accent underline underline-offset-2">
          {t('actions.backToHome', { ns: 'common' })}
        </Link>
      </div>
    </main>
  );
}
