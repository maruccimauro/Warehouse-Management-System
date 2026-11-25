import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Icon } from './icons';

interface InfoNoticeProps {
  children: ReactNode;
  // Región viva: el texto cambia con lo que se ingresa (la vista previa de LS10).
  live?: boolean;
}

// Aviso informativo o de guía de una pantalla: una barra fina superior con el ícono de
// información y el rótulo, y el texto debajo. Distingue la guía de los datos y de los mensajes
// de la barra de estado, que responden a una acción.
export function InfoNotice({ children, live = false }: InfoNoticeProps) {
  const { t } = useTranslation('common');

  return (
    <section className="info-notice" aria-live={live ? 'polite' : undefined}>
      <p className="info-notice-bar">
        <Icon name="info" />
        {t('info')}
      </p>
      <div className="grid gap-1 px-3 py-2">{children}</div>
    </section>
  );
}
