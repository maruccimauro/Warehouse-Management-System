import { Fragment, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useParams } from 'react-router';
import { findTransaction } from '@/app/transactions';
import { useStatusBar } from './statusMessages';

function UnknownTransaction({ code }: { code: string }) {
  const { t } = useTranslation('shell');
  const { show } = useStatusBar();

  useEffect(() => {
    show({ type: 'E', text: t('command.unknownTransaction', { code }) });
  }, [show, t, code]);

  return <Navigate to="/" replace />;
}

// Ruta /t/:code. Un código inexistente (por ejemplo, un enlace viejo) vuelve al menú con el
// mensaje en la barra de estado, como el campo de comando.
export function TransactionScreen() {
  const { code = '' } = useParams();
  const transaction = findTransaction(code);

  if (transaction === undefined) {
    return <UnknownTransaction code={code.toUpperCase()} />;
  }

  // key: cambiar de transacción descarta el estado de la anterior.
  return <Fragment key={transaction.code}>{transaction.element}</Fragment>;
}
