import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

export function NotFoundPage() {
  const { t } = useTranslation(['notFound', 'common']);

  return (
    <main>
      <h1>{t('title')}</h1>
      <p>{t('description')}</p>
      <Link to="/">{t('actions.backToHome', { ns: 'common' })}</Link>
    </main>
  );
}
