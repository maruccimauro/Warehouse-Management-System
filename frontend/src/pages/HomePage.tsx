import { useTranslation } from 'react-i18next';

// Pantalla inicial: el menú de acceso. El título lo pone el armazón y el árbol está en el
// panel lateral.
export function HomePage() {
  const { t } = useTranslation('home');

  return (
    <div className="grid max-w-2xl gap-2">
      <p>{t('description')}</p>
      <p className="text-ink-muted">{t('hint')}</p>
    </div>
  );
}
