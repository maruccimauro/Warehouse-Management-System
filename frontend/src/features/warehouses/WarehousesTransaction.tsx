import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { sessionQueryKey } from '@/features/auth/session';
import { HelpLink } from '@/shared/help/HelpLink';
import { codeRule, required, textRule } from '@/shared/ui/form/fieldRules';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort, GridSource } from '@/shared/ui/grid/gridTypes';
import {
  addressMaxLength,
  createWarehouse,
  fetchWarehouses,
  updateWarehouse,
  warehousesQueryKey,
  type Warehouse, normalizeCode } from './warehousesApi';

const warehouseSource: GridSource<Warehouse> = { kind: 'server', queryKey: [...warehousesQueryKey, 'grid'], fetchPage: fetchWarehouses };
const exportName = 'depositos';
const byCode: GridSort = { columnId: 'code', desc: false };

type Screen = { kind: 'list' } | { kind: 'detail'; mode: 'create' | 'display' | 'change'; warehouse?: Warehouse };

// ZW01: depósitos (E2.12, D5). Lista en la grilla y, con doble clic, la ficha en modo
// visualizar, con Modificar y Crear en la barra de aplicación. Activar y desactivar son
// acciones con confirmación: desactivar un depósito detiene su operación (REG-015).
export function WarehousesTransaction() {
  const [screen, setScreen] = useState<Screen>({ kind: 'list' });

  if (screen.kind === 'list') {
    return (
      <WarehouseList
        onOpen={(warehouse) => {
          setScreen({ kind: 'detail', mode: 'display', warehouse });
        }}
        onCreate={() => {
          setScreen({ kind: 'detail', mode: 'create' });
        }}
      />
    );
  }

  return (
    <WarehouseDetail
      // Cambiar de modo o de depósito vuelve a montar la ficha con sus valores.
      key={`${screen.mode}-${String(screen.warehouse?.id ?? 'new')}`}
      mode={screen.mode}
      warehouse={screen.warehouse}
      onModeChange={(mode, warehouse) => {
        setScreen({ kind: 'detail', mode, warehouse });
      }}
      onBack={() => {
        setScreen({ kind: 'list' });
      }}
    />
  );
}

function WarehouseList({ onOpen, onCreate }: { onOpen: (warehouse: Warehouse) => void; onCreate: () => void }) {
  const { t } = useTranslation('warehouses');
  const { show } = useStatusBar();

  const columns = useMemo(
    (): GridColumn<Warehouse>[] => [
      { id: 'code', header: t('fields.code'), value: (row) => row.code, sortable: true, filter: 'contains' },
      { id: 'name', header: t('fields.name'), value: (row) => row.name, sortable: true, filter: 'contains' },
      { id: 'address', header: t('fields.address'), value: (row) => row.address },
      {
        id: 'is_active',
        header: t('fields.status'),
        // El filtro booleano del listado admite 1 y 0 (regla boolean de Laravel).
        value: (row) => (row.is_active ? '1' : '0'),
        format: (value) => t(value === '1' ? 'statuses.active' : 'statuses.inactive'),
        filter: 'equals',
        options: [
          { value: '1', label: t('statuses.active') },
          { value: '0', label: t('statuses.inactive') },
        ],
      },
    ],
    [t],
  );

  const actions = [{ id: 'create', label: t('actions.create'), onClick: onCreate }];

  return (
    <div className="grid gap-3">
      <ApplicationToolbar label={t('list.actions')} actions={actions} />
      <DataGrid
        label={t('list.title')}
        columns={columns}
        source={warehouseSource}
        rowKey={(row) => row.id}
        exportName={exportName}
        onMessage={show}
        onRowActivate={onOpen}
        initialSort={byCode}
      />
    </div>
  );
}

const warehouseSchema = z.object({
  code: codeRule(20),
  name: required().max(120),
  address: textRule(addressMaxLength),
});

interface WarehouseDetailProps {
  mode: 'create' | 'display' | 'change';
  warehouse: Warehouse | undefined;
  onModeChange: (mode: 'display' | 'change', warehouse: Warehouse) => void;
  onBack: () => void;
}

function WarehouseDetail({ mode, warehouse, onModeChange, onBack }: WarehouseDetailProps) {
  const { t } = useTranslation('warehouses');
  const { show } = useStatusBar();
  const queryClient = useQueryClient();
  const [confirming, setConfirming] = useState(false);
  const [current, setCurrent] = useState(warehouse);

  const defaultValues = useMemo(
    () => ({ code: current?.code ?? '', name: current?.name ?? '', address: current?.address ?? '' }),
    [current],
  );

  // Un depósito nuevo o modificado cambia la lista, los campos de depósito y, para quien opera
  // en todos, los depósitos de la sesión (me.warehouses).
  const refresh = () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: warehousesQueryKey }),
      queryClient.invalidateQueries({ queryKey: sessionQueryKey }),
    ]);

  const { Form, Section, Field } = useTransactionForm({
    schema: warehouseSchema,
    defaultValues,
    namespace: 'warehouses',
    readOnly: mode === 'display',
    savedMessage: mode === 'create' ? t('messages.created') : t('messages.saved'),
    saveFailedMessage: t('messages.saveFailed'),
    onBack,
    onSave: async ({ code, name, address }) => {
      const saved =
        mode === 'create'
          ? await createWarehouse({ code: normalizeCode(code), name, address: address === '' ? null : address })
          : await updateWarehouse(current?.id ?? 0, { name, address: address === '' ? null : address });

      void refresh();
      onModeChange('display', saved);
    },
  });

  const toggleActive = async () => {
    if (current === undefined) {
      return;
    }

    setConfirming(false);

    try {
      const saved = await updateWarehouse(current.id, { is_active: !current.is_active });

      setCurrent(saved);
      void refresh();
      show({ type: 'S', text: t(saved.is_active ? 'messages.activated' : 'messages.deactivated', { code: saved.code }) });
    } catch (error) {
      show(errorMessage(error, t('messages.saveFailed')));
    }
  };

  const actions: ApplicationAction[] = [];

  if (mode === 'display' && current !== undefined) {
    actions.push({
      id: 'change',
      label: t('actions.change'),
      primary: true,
      onClick: () => {
        onModeChange('change', current);
      },
    });
  }

  if (mode !== 'create' && current !== undefined) {
    actions.push({
      id: 'toggle',
      label: current.is_active ? t('actions.deactivate') : t('actions.activate'),
      onClick: () => {
        setConfirming(true);
      },
    });
  }

  return (
    <div className="grid gap-3">
      <ApplicationToolbar label={t('detail.actions')} actions={actions} />
      <Form>
        <Section name="general">
          <Field name="code" required readOnly={mode !== 'create'} className="w-48 uppercase" />
          <Field name="name" required />
          <Field name="address" />
          {current !== undefined && (
            <p className="text-ink-muted">
              {t('fields.status')}:{' '}
              <HelpLink helpKey={current.is_active ? 'state.warehouse.active' : 'state.warehouse.inactive'}>
                {t(current.is_active ? 'statuses.active' : 'statuses.inactive')}
              </HelpLink>
            </p>
          )}
        </Section>
      </Form>
      {current !== undefined && (
        <ConfirmDialog
          open={confirming}
          title={current.is_active ? t('confirm.deactivateTitle') : t('confirm.activateTitle')}
          message={t(current.is_active ? 'confirm.deactivate' : 'confirm.activate', { code: current.code })}
          confirmLabel={current.is_active ? t('actions.deactivate') : t('actions.activate')}
          cancelLabel={t('confirm.cancel')}
          onConfirm={() => {
            void toggleActive();
          }}
          onCancel={() => {
            setConfirming(false);
          }}
        />
      )}
    </div>
  );
}
