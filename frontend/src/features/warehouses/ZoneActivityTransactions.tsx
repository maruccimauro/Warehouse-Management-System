import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useTransactionActions } from '@/app/shell/shellActions';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useInitialScreenFlow, type MaintenanceActivity } from '@/app/shell/useInitialScreenFlow';
import type { WarehouseSummary } from '@/features/auth/authApi';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { WarehouseScopeProvider, ZoneField } from './LocationFields';
import { useDefaultWarehouse } from './useDefaultWarehouse';
import { VisibleWarehouseField, WarehouseField } from './WarehouseField';
import { useVisibleWarehouses, useZones } from './warehouseQueries';
import { normalizeCode, type Zone } from './warehousesApi';
import { ZoneDetail } from './ZonesTransaction';

// ZW21, ZW22 y ZW23: crear, modificar y visualizar una zona, cada acción con su transacción
// (ADR-016), además de la gestión con lista y ficha de ZW02. Usan la misma ficha que ZW02.
// La pantalla inicial pide el depósito y, salvo al crear, la zona; crear abre la ficha del
// depósito elegido y el código repetido se informa al grabar (VALUE_ALREADY_EXISTS).

interface ZoneTarget {
  warehouse: WarehouseSummary;
  // undefined al crear: la zona todavía no existe.
  zone: Zone | undefined;
}

interface ZoneEntry {
  warehouse: string;
  zone: string;
}

export function CreateZoneTransaction() {
  return <ZoneActivity activity="create" />;
}

export function ChangeZoneTransaction() {
  return <ZoneActivity activity="change" />;
}

export function DisplayZoneTransaction() {
  return <ZoneActivity activity="display" />;
}

function ZoneActivity({ activity }: { activity: MaintenanceActivity }) {
  const flow = useInitialScreenFlow((next: ZoneTarget): ZoneEntry => ({ warehouse: next.warehouse.code, zone: next.zone?.code ?? '' }));

  if (flow.target === undefined) {
    return <ZoneInitialScreen activity={activity} initial={flow.last} onContinue={flow.open} />;
  }

  return (
    <ZoneDetail
      warehouse={flow.target.warehouse}
      mode={activity}
      zone={flow.target.zone}
      standalone
      // Grabar vuelve a la pantalla inicial con lo ingresado, lista para la siguiente.
      onModeChange={flow.close}
      onBack={flow.close}
    />
  );
}

interface ZoneInitialScreenProps {
  activity: MaintenanceActivity;
  initial: ZoneEntry | undefined;
  onContinue: (target: ZoneTarget) => void;
}

function ZoneInitialScreen({ activity, initial, onContinue }: ZoneInitialScreenProps) {
  const { t } = useTranslation('zones');
  const { show } = useStatusBar();
  const user = useAuthenticatedUser();
  const visible = useVisibleWarehouses();
  const defaultWarehouse = useDefaultWarehouse();
  // Visualizar admite los depósitos que el usuario consulta; crear y modificar, donde opera.
  const consult = activity === 'display';
  const warehouses: readonly WarehouseSummary[] = consult ? (visible.data ?? []) : user.warehouses;

  const fields = useMemo((): SelectionField[] => {
    const warehouseField: SelectionField = {
      kind: 'parameter',
      id: 'warehouse',
      label: t('fields.warehouse'),
      as: consult ? VisibleWarehouseField : WarehouseField,
      required: true,
    };

    return activity === 'create'
      ? [warehouseField]
      : [warehouseField, { kind: 'parameter', id: 'zone', label: t('fields.code'), as: ZoneInput, required: true }];
  }, [t, consult, activity]);

  const selection = useSelectionScreen(fields, {
    warehouse: initial?.warehouse ?? defaultWarehouse ?? '',
    zone: initial?.zone ?? '',
  });
  const warehouseCode = normalizeCode(parameterValue(selection.values, 'warehouse'));
  const warehouse = warehouses.find((candidate) => candidate.code === warehouseCode);
  const zones = useZones(warehouse?.id);

  useTransactionActions({
    execute: () => {
      const values = selection.validate(show);

      // El campo ya informó un depósito o una zona inexistentes.
      if (values === undefined || warehouse === undefined) {
        return;
      }

      if (activity === 'create') {
        onContinue({ warehouse, zone: undefined });

        return;
      }

      const zone = zones.data?.find((candidate) => candidate.code === normalizeCode(parameterValue(values, 'zone')));

      if (zone !== undefined) {
        onContinue({ warehouse, zone });
      }
    },
  });

  return (
    <WarehouseScopeProvider scope={{ warehouseId: warehouse?.id, zones: zones.data ?? [], zonesLoading: zones.isFetching }}>
      <div className="grid gap-3">
        <SelectionScreen selection={selection} title={t(`initial.${activity}`)} onMessage={show} />
        <InfoNotice>{t(activity === 'create' ? 'initial.createHint' : 'initial.hint')}</InfoNotice>
      </div>
    </WarehouseScopeProvider>
  );
}

// Zona con F4 local del depósito elegido, con tabla de verificación: F8 no continúa con una
// zona que no existe.
function ZoneInput(props: SelectionInputProps) {
  return <ZoneField {...props} />;
}
