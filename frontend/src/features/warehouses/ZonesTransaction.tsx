import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useTransactionActions } from '@/app/shell/shellActions';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import type { WarehouseSummary } from '@/features/auth/authApi';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort, GridSource } from '@/shared/ui/grid/gridTypes';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import type { SelectionField } from '@/shared/ui/selection/selectionTypes';
import { parameterValue } from '@/shared/ui/selection/selection';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { codeRule, required } from '@/shared/ui/form/fieldRules';
import { useDefaultWarehouse } from './useDefaultWarehouse';
import { WarehouseField } from './WarehouseField';
import { createZone, fetchZones, updateZone, zonesQueryKey, zoneTypes, type Zone, type ZoneType, normalizeCode } from './warehousesApi';

const byCode: GridSort = { columnId: 'code', desc: false };

type Screen =
  | { kind: 'selection' }
  | { kind: 'list'; warehouse: WarehouseSummary }
  | { kind: 'detail'; warehouse: WarehouseSummary; mode: 'create' | 'display' | 'change'; zone?: Zone };

// ZW02: zonas de un depósito (E2.12, D5). Pantalla inicial con el depósito (F8), la lista de
// zonas y la ficha. Las zonas de sistema (recepción, empaque y despacho) las crea el depósito:
// se ven y cambian de nombre, pero no de tipo (DM-19).
export function ZonesTransaction() {
  const { t } = useTranslation('zones');
  const { show } = useStatusBar();
  const user = useAuthenticatedUser();
  const defaultWarehouse = useDefaultWarehouse();
  const [screen, setScreen] = useState<Screen>({ kind: 'selection' });

  const fields = useMemo(
    (): SelectionField[] => [{ kind: 'parameter', id: 'warehouse', label: t('fields.warehouse'), as: WarehouseField, required: true }],
    [t],
  );
  const selection = useSelectionScreen(fields, { warehouse: defaultWarehouse ?? '' });

  const execute = () => {
    const values = selection.validate(show);
    const code = values === undefined ? undefined : parameterValue(values, 'warehouse');
    const warehouse = user.warehouses.find((candidate) => candidate.code === code);

    if (warehouse !== undefined) {
      setScreen({ kind: 'list', warehouse });
    }
  };

  if (screen.kind === 'selection') {
    return <ZoneSelection selection={selection} onExecute={execute} />;
  }

  if (screen.kind === 'list') {
    return (
      <ZoneList
        warehouse={screen.warehouse}
        onBack={() => {
          setScreen({ kind: 'selection' });
        }}
        onOpen={(zone) => {
          setScreen({ kind: 'detail', warehouse: screen.warehouse, mode: 'display', zone });
        }}
        onCreate={() => {
          setScreen({ kind: 'detail', warehouse: screen.warehouse, mode: 'create' });
        }}
      />
    );
  }

  return (
    <ZoneDetail
      key={`${screen.mode}-${String(screen.zone?.id ?? 'new')}`}
      warehouse={screen.warehouse}
      mode={screen.mode}
      zone={screen.zone}
      onModeChange={(mode, zone) => {
        setScreen({ kind: 'detail', warehouse: screen.warehouse, mode, zone });
      }}
      onBack={() => {
        setScreen({ kind: 'list', warehouse: screen.warehouse });
      }}
    />
  );
}

function ZoneSelection({ selection, onExecute }: { selection: ReturnType<typeof useSelectionScreen>; onExecute: () => void }) {
  const { t } = useTranslation('zones');
  const { show } = useStatusBar();

  useTransactionActions({ execute: onExecute });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t('selection.title')} onMessage={show} />
      <p className="text-xs text-ink-muted">{t('selection.hint')}</p>
    </div>
  );
}

interface ZoneListProps {
  warehouse: WarehouseSummary;
  onBack: () => void;
  onOpen: (zone: Zone) => void;
  onCreate: () => void;
}

function ZoneList({ warehouse, onBack, onOpen, onCreate }: ZoneListProps) {
  const { t } = useTranslation('zones');
  const { show } = useStatusBar();

  useTransactionActions({ back: onBack });

  const columns = useMemo(
    (): GridColumn<Zone>[] => [
      { id: 'code', header: t('fields.code'), value: (row) => row.code, sortable: true, filter: 'contains' },
      { id: 'name', header: t('fields.name'), value: (row) => row.name, sortable: true, filter: 'contains' },
      {
        id: 'type',
        header: t('fields.type'),
        value: (row) => row.type,
        format: (value) => t(`types.${String(value) as ZoneType}`),
        sortable: true,
        filter: 'in',
        options: zoneTypes.map((type) => ({ value: type, label: t(`types.${type}`) })),
      },
      {
        id: 'is_system',
        header: t('fields.isSystem'),
        value: (row) => (row.is_system ? '1' : '0'),
        format: (value) => t(value === '1' ? 'yes' : 'no'),
        filter: 'equals',
        options: [
          { value: '1', label: t('yes') },
          { value: '0', label: t('no') },
        ],
      },
    ],
    [t],
  );

  const actions = [{ id: 'create', label: t('actions.create'), onClick: onCreate }];
  const source = useMemo(
    (): GridSource<Zone> => ({
      kind: 'server',
      queryKey: [...zonesQueryKey(warehouse.id), 'grid'],
      fetchPage: (params, signal) => fetchZones(warehouse.id, params, signal),
    }),
    [warehouse.id],
  );
  const exportName = `zonas-${warehouse.code.toLowerCase()}`;

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('list.warehouse', { code: warehouse.code, name: warehouse.name })}</p>
      <ApplicationToolbar label={t('list.actions')} actions={actions} />
      <DataGrid
        label={t('list.title', { code: warehouse.code })}
        columns={columns}
        source={source}
        rowKey={(row) => row.id}
        exportName={exportName}
        onMessage={show}
        onRowActivate={onOpen}
        initialSort={byCode}
      />
    </div>
  );
}

const zoneSchema = z.object({
  code: codeRule(20),
  name: required().max(120),
  type: z.enum(zoneTypes),
});

interface ZoneDetailProps {
  warehouse: WarehouseSummary;
  mode: 'create' | 'display' | 'change';
  zone: Zone | undefined;
  onModeChange: (mode: 'display' | 'change', zone: Zone) => void;
  onBack: () => void;
}

function ZoneDetail({ warehouse, mode, zone, onModeChange, onBack }: ZoneDetailProps) {
  const { t } = useTranslation('zones');
  const queryClient = useQueryClient();

  const defaultValues = useMemo(
    () => ({ code: zone?.code ?? '', name: zone?.name ?? '', type: zone?.type ?? 'storage' }),
    [zone],
  );

  const { Form, Section, Field } = useTransactionForm({
    schema: zoneSchema,
    defaultValues,
    namespace: 'zones',
    readOnly: mode === 'display',
    savedMessage: mode === 'create' ? t('messages.created') : t('messages.saved'),
    saveFailedMessage: t('messages.saveFailed'),
    onBack,
    onSave: async ({ code, name, type }) => {
      const saved =
        mode === 'create'
          ? await createZone(warehouse.id, { code: normalizeCode(code), name, type })
          : await updateZone(zone?.id ?? 0, zone?.is_system === true ? { name } : { name, type });

      void queryClient.invalidateQueries({ queryKey: zonesQueryKey(warehouse.id) });
      onModeChange('display', saved);
    },
  });

  const typeOptions = zoneTypes.map((type) => ({ value: type, label: t(`types.${type}`) }));
  const actions: ApplicationAction[] =
    mode === 'display' && zone !== undefined
      ? [
          {
            id: 'change',
            label: t('actions.change'),
            onClick: () => {
              onModeChange('change', zone);
            },
          },
        ]
      : [];

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('list.warehouse', { code: warehouse.code, name: warehouse.name })}</p>
      <ApplicationToolbar label={t('detail.actions')} actions={actions} />
      <Form>
        <Section name="general">
          <Field name="code" required readOnly={mode !== 'create'} className="w-48 uppercase" />
          <Field name="name" required />
          <Field
            name="type"
            options={typeOptions}
            readOnly={zone?.is_system === true}
            description={zone?.is_system === true ? t('detail.systemZone') : undefined}
          />
        </Section>
      </Form>
    </div>
  );
}
