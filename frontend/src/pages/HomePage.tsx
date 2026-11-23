import { useTranslation } from 'react-i18next';

export function HomePage() {
  const { t } = useTranslation('home');

  return (
    <main>
      <h1>{t('title')}</h1>
      <p>{t('description')}</p>
    </main>
  );
}
