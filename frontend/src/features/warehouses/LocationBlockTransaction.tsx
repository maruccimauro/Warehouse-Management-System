import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { HelpLink } from '@/shared/help/HelpLink';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog';
import { required } from '@/shared/ui/form/fieldRules';
import { LocationInitialScreen, type LocationTarget } from './LocationInitialScreen';
import { blockLocation, blockReasonMaxLength, locationsQueryKey, unblockLocation, type Location } from './warehousesApi';

// ZW05: bloqueo y desbloqueo de ubicaciones (E2.12, D2). Permiso propio (locations.block),
// separado del mantenimiento: el supervisor bloquea sin poder modificar la ficha. Bloquear
// exige un motivo y solo parte de una ubicación activa; desbloquear la devuelve a activa.
export function LocationBlockTransaction() {
  const [target, setTarget] = useState<LocationTarget>();
  const [last, setLast] = useState<{ warehouse: string; location: string }>();

  if (target?.location === undefined) {
    return (
      <LocationInitialScreen
        activity="block"
        initial={last}
        onContinue={(next) => {
          setLast({ warehouse: next.warehouse.code, location: next.code });
          setTarget(next);
        }}
      />
    );
  }

  return (
    <BlockDetail
      target={target}
      location={target.location}
      onDone={() => {
        setTarget(undefined);
      }}
    />
  );
}

const blockSchema = z.object({ reason: required().max(blockReasonMaxLength) });

interface BlockDetailProps {
  target: LocationTarget;
  location: Location;
  onDone: () => void;
}

function BlockDetail({ target, location, onDone }: BlockDetailProps) {
  const { t } = useTranslation('locations');
  const { show } = useStatusBar();
  const queryClient = useQueryClient();
  const [confirmingUnblock, setConfirmingUnblock] = useState(false);
  const defaultValues = useMemo(() => ({ reason: '' }), []);
  const canBlock = location.status === 'active';
  const canUnblock = location.status === 'blocked';

  const refresh = () => queryClient.invalidateQueries({ queryKey: locationsQueryKey(target.warehouse.id) });

  const { Form, Section, Field, save } = useTransactionForm({
    schema: blockSchema,
    defaultValues,
    namespace: 'locations',
    // Sin bloqueo posible no hay nada que grabar: la ficha queda en solo lectura.
    readOnly: !canBlock,
    savedMessage: t('block.blocked', { code: location.code }),
    saveFailedMessage: t('block.failed'),
    onBack: onDone,
    onSave: async ({ reason }) => {
      await blockLocation(location.id, reason);
      void refresh();
      onDone();
    },
  });

  const unblock = async () => {
    setConfirmingUnblock(false);

    try {
      await unblockLocation(location.id);
      void refresh();
      show({ type: 'S', text: t('block.unblocked', { code: location.code }) });
      onDone();
    } catch (error) {
      show(errorMessage(error, t('block.failed')));
    }
  };

  const actions: ApplicationAction[] = [];

  if (canBlock) {
    actions.push({
      id: 'block',
      label: t('block.block'),
      onClick: () => {
        void save();
      },
    });
  }

  if (canUnblock) {
    actions.push({
      id: 'unblock',
      label: t('block.unblock'),
      onClick: () => {
        setConfirmingUnblock(true);
      },
    });
  }

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('detail.warehouse', { code: target.warehouse.code, name: target.warehouse.name })}</p>
      <ApplicationToolbar label={t('block.actions')} actions={actions} />
      <section className="grid max-w-4xl gap-1 rounded-sm border border-border bg-surface px-3 py-2">
        <p>
          {t('fields.code')}: <span className="font-mono">{location.code}</span>
        </p>
        <p>
          {t('fields.status')}:{' '}
          <HelpLink helpKey={`state.location.${location.status}`}>{t(`statuses.${location.status}`)}</HelpLink>
        </p>
        {location.status_reason !== null && (
          <p>
            {t('fields.statusReason')}: {location.status_reason}
          </p>
        )}
        {!canBlock && !canUnblock && <p className="text-ink-muted">{t('block.notAllowed', { status: t(`statuses.${location.status}`) })}</p>}
      </section>
      {canBlock && (
        <Form>
          <Section name="block">
            <Field name="reason" required description={t('block.reasonHint', { max: blockReasonMaxLength })} />
          </Section>
        </Form>
      )}
      {canBlock && <p className="text-xs text-ink-muted">{t('block.hint')}</p>}
      <ConfirmDialog
        open={confirmingUnblock}
        title={t('block.unblockTitle')}
        message={t('block.unblockConfirm', { code: location.code })}
        confirmLabel={t('block.unblock')}
        cancelLabel={t('block.cancel')}
        onConfirm={() => {
          void unblock();
        }}
        onCancel={() => {
          setConfirmingUnblock(false);
        }}
      />
    </div>
  );
}
