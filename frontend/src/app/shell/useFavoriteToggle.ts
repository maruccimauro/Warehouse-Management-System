import { useTranslation } from 'react-i18next';
import { useUserPreferences } from '@/features/preferences/userPreferences';
import { useStatusBar } from './statusMessages';

// Agrega o quita una transacción de favoritos e informa el resultado en la barra de estado.
// Lo usan la barra estándar y los menús contextuales.
export function useFavoriteToggle(): (code: string) => void {
  const { t } = useTranslation('shell');
  const { toggleFavorite } = useUserPreferences();
  const { show } = useStatusBar();

  return (code: string) => {
    const added = toggleFavorite(code);

    show({ type: 'S', text: t(added ? 'toolbar.favoriteAdded' : 'toolbar.favoriteRemoved', { code }) });
  };
}
