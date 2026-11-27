import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { transactionPath } from '@/app/shell/command';
import { Icon } from '@/shared/ui/icons';
import { fetchUnreadCount, unreadCountQueryKey } from './inboxApi';

// Sondeo del contador: un aviso nuevo aparece en el encabezado en menos de un minuto sin
// recargar. La consulta es un COUNT sobre un índice parcial de los no leídos del usuario.
export const unreadCountRefetchMs = 60_000;

export const inboxTransactionCode = 'SBWP';

// Contador de no leídos del encabezado (E3.27, D16.9): abre la Bandeja de entrada (SBWP).
// La navegación pasa por el router, así que con cambios sin grabar pide confirmación.
export function UnreadCounter() {
  const { t } = useTranslation('inbox');
  const unread = useQuery({
    queryKey: unreadCountQueryKey,
    queryFn: ({ signal }) => fetchUnreadCount(signal),
    refetchInterval: unreadCountRefetchMs,
    refetchOnWindowFocus: true,
  });
  const count = unread.data ?? 0;
  const label = count === 0 ? t('counter.none') : t('counter.unread', { count });

  return (
    <Link
      to={transactionPath(inboxTransactionCode)}
      className="inline-flex min-h-7 items-center gap-1 rounded-sm px-2 text-shell-fg hover:bg-shell-raised pointer-coarse:min-h-11"
      aria-label={label}
      title={label}
    >
      <Icon name="inbox" />
      {count > 0 && (
        <span aria-hidden="true" className="min-w-5 rounded-full bg-accent px-1.5 text-center text-xs font-semibold text-white">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Link>
  );
}
