import { useTranslation } from 'react-i18next';
import { HelpLink } from '@/shared/help/HelpLink';
import { isHelpKey } from '@/shared/help/helpLinks';
import { Icon, type IconName } from '@/shared/ui/icons';
import { useStatusBar, type MessageType, type StatusMessage } from './statusMessages';

// El color de cada tipo lo pone .status-message según data-type (index.css).
const icons: Record<MessageType, IconName> = {
  S: 'success',
  W: 'warning',
  E: 'error',
  I: 'info',
};

// Código del mensaje: si la wiki lo explica, es un enlace a su entrada, como el texto largo
// del mensaje que SAP abre con doble clic en la barra de estado (D8 de E2.16).
function MessageId({ id }: { id: string }) {
  const { t } = useTranslation('shell');
  const helpKey = `message.${id}`;
  const className = 'shrink-0 font-mono text-xs text-ink-muted';

  if (!isHelpKey(helpKey)) {
    return <span className={className}>({id})</span>;
  }

  return (
    <span className={className}>
      (
      <HelpLink helpKey={helpKey} label={t('statusBar.messageHelp', { id })}>
        {id}
      </HelpLink>
      )
    </span>
  );
}

function MessageContent({ message }: { message: StatusMessage }) {
  const { t } = useTranslation('shell');
  return (
    <span className="status-message-content inline-flex min-w-0 items-center gap-1.5">
      <span className="status-message-icon">
        <Icon name={icons[message.type]} />
      </span>
      <span className="visually-hidden">{t(`statusBar.types.${message.type}`)}: </span>
      <span className="truncate">{message.text}</span>
      {message.id !== undefined && <MessageId id={message.id} />}
    </span>
  );
}

interface StatusBarProps {
  transactionCode: string | undefined;
  defaultWarehouse: string | undefined;
  language: string;
}

// Barra de estado de SAP GUI: el resultado de la última acción a la izquierda y los datos
// de la sesión a la derecha. Dos regiones vivas fijas (una cortés y una asertiva) para que
// cada mensaje se anuncie de forma confiable: los errores interrumpen, el resto espera.
// Cada mensaje nuevo pinta el área del mensaje con el color de su tipo, de izquierda a
// derecha, con un destello al completarse; la clave sequence reinicia la animación aunque el
// mensaje se repita. Los datos de la sesión, a la derecha, no se animan.
export function StatusBar({ transactionCode, defaultWarehouse, language }: StatusBarProps) {
  const { t } = useTranslation('shell');
  const { message, sequence } = useStatusBar();
  const isError = message?.type === 'E';

  return (
    <footer
      aria-label={t('statusBar.label')}
      className="flex min-h-7 items-stretch gap-4 border-t border-border bg-toolbar pe-3 text-xs"
    >
      <div
        key={sequence}
        data-type={message?.type}
        className={`flex min-w-0 flex-1 flex-col justify-center ps-3 pe-2${message === undefined ? '' : ' status-message'}`}
      >
        <p role="status" className="min-w-0">
          {message !== undefined && !isError && <MessageContent message={message} />}
        </p>
        <p role="alert" className="min-w-0">
          {message !== undefined && isError && <MessageContent message={message} />}
        </p>
      </div>
      <dl className="flex shrink-0 items-center gap-3 font-mono text-ink-muted max-sm:hidden">
        <div className="flex gap-1">
          <dt className="visually-hidden">{t('statusBar.transaction')}</dt>
          <dd>{transactionCode ?? '–'}</dd>
        </div>
        <div className="flex gap-1">
          <dt>LGN</dt>
          <dd>{defaultWarehouse ?? t('statusBar.none')}</dd>
        </div>
        <div className="flex gap-1">
          <dt className="visually-hidden">{t('statusBar.language')}</dt>
          <dd className="uppercase">{language}</dd>
        </div>
      </dl>
    </footer>
  );
}
