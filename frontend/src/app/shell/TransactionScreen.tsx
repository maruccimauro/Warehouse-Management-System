import { Component, Suspense, useEffect, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useOutletContext, useParams } from 'react-router';
import { Button } from '@/shared/ui/Button';
import { useStatusBar } from './statusMessages';
import { useTransactionAccess } from './useTransactionAccess';

// Contexto que el armazón pasa al Outlet: avisa cuándo el contenido de la transacción ya
// está en el DOM, para mover el foco a su primer campo después de la carga diferida.
export interface TransactionOutletContext {
  onTransactionReady: (code: string | undefined) => void;
}

function RejectedTransaction({ message }: { message: string }) {
  const { show } = useStatusBar();

  useEffect(() => {
    show({ type: 'E', text: message });
  }, [show, message]);

  return <Navigate to="/" replace />;
}

function ReadySignal({ code }: { code: string }) {
  const { onTransactionReady } = useOutletContext<TransactionOutletContext>();

  useEffect(() => {
    onTransactionReady(code);

    return () => {
      onTransactionReady(undefined);
    };
  }, [code, onTransactionReady]);

  return null;
}

function TransactionLoading() {
  const { t } = useTranslation('shell');

  return (
    <p role="status" className="text-ink-muted">
      {t('transaction.loading')}
    </p>
  );
}

function TransactionLoadError({ code }: { code: string }) {
  const { t } = useTranslation('shell');

  return (
    <div role="alert" className="grid max-w-2xl gap-2 rounded-sm border border-error bg-error-soft p-3 text-error">
      <p>{t('transaction.loadFailed', { code })}</p>
      <Button
        className="w-fit"
        onClick={() => {
          window.location.reload();
        }}
      >
        {t('transaction.reload')}
      </Button>
    </div>
  );
}

interface BoundaryProps {
  code: string;
  children: ReactNode;
}

// Un chunk que no carga (por ejemplo, la pestaña quedó abierta durante un despliegue y el
// archivo ya no existe) o un error de render dentro de la transacción no tira abajo el
// armazón: se informa y se ofrece recargar. React solo admite límites de error como clase.
class TransactionErrorBoundary extends Component<BoundaryProps, { failed: boolean }> {
  override state = { failed: false };

  static getDerivedStateFromError(): { failed: boolean } {
    return { failed: true };
  }

  override render() {
    return this.state.failed ? <TransactionLoadError code={this.props.code} /> : this.props.children;
  }
}

// Ruta /t/:code. Un código inexistente (por ejemplo, un enlace viejo) o sin autorización
// vuelve al menú con el mensaje en la barra de estado, como el campo de comando.
export function TransactionScreen() {
  const { t } = useTranslation('shell');
  const { code = '' } = useParams();
  const access = useTransactionAccess().resolve(code);

  if (access.status === 'unknown') {
    return <RejectedTransaction message={t('command.unknownTransaction', { code: code.toUpperCase() })} />;
  }

  if (access.status === 'forbidden') {
    return <RejectedTransaction message={t('command.notAuthorized', { code: access.transaction.code })} />;
  }

  const { code: transactionCode, component: TransactionComponent } = access.transaction;

  // key: cambiar de transacción descarta el estado de la anterior, también el del límite de error.
  return (
    <TransactionErrorBoundary key={transactionCode} code={transactionCode}>
      <Suspense fallback={<TransactionLoading />}>
        <TransactionComponent />
        <ReadySignal code={transactionCode} />
      </Suspense>
    </TransactionErrorBoundary>
  );
}
