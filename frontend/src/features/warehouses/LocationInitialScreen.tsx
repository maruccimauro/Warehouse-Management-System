import { useEffect, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import type { WarehouseSummary } from '@/features/auth/authApi';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { LocationField, WarehouseScopeProvider } from './LocationFields';
import { locationLinkParams } from './locationLinks';
import { useDefaultWarehouse } from './useDefaultWarehouse';
import { VisibleWarehouseField, WarehouseField } from './WarehouseField';
import { useVisibleWarehouses, useZones } from './warehouseQueries';
import { findLocation, normalizeCode, type Location } from './warehousesApi';

export type LocationActivity = 'create' | 'change' | 'display' | 'block';

export interface LocationTarget {
  warehouse: WarehouseSummary;
  code: string;
  // La ubicación leída; undefined al crear (todavía no existe).
  location: Location | undefined;
}

interface LocationInitialScreenProps {
  activity: LocationActivity;
  onContinue: (target: LocationTarget) => void;
  // Valores de la última entrada: F3 desde la ficha vuelve con lo ingresado.
  initial: { warehouse: string; location: string } | undefined;
}

// Pantalla inicial de crear, modificar, visualizar y bloquear una ubicación (E2.12): depósito
// y ubicación, y F8 lee el registro. Como la pantalla inicial de las transacciones de datos
// maestros: crear exige que el código no exista y el resto, que exista.
export function LocationInitialScreen({ activity, onContinue, initial }: LocationInitialScreenProps) {
  const { t } = useTranslation('locations');
  const { show } = useStatusBar();
  const user = useAuthenticatedUser();
  const visible = useVisibleWarehouses();
  const defaultWarehouse = useDefaultWarehouse();
  const [searchParams, setSearchParams] = useSearchParams();
  // Consultar admite los depósitos que el usuario ve; el resto opera solo donde está asignado.
  const consult = activity === 'display';
  const warehouses: readonly WarehouseSummary[] = consult ? (visible.data ?? []) : user.warehouses;

  const fields = useMemo(
    (): SelectionField[] => [
      { kind: 'parameter', id: 'warehouse', label: t('fields.warehouse'), as: consult ? VisibleWarehouseField : WarehouseField, required: true },
      activity === 'create'
        ? { kind: 'parameter', id: 'code', label: t('fields.code'), normalize: normalizeCode, required: true }
        : { kind: 'parameter', id: 'code', label: t('fields.code'), as: LocationInput, required: true },
    ],
    [t, consult, activity],
  );

  const linked = {
    warehouse: searchParams.get(locationLinkParams.warehouse),
    location: searchParams.get(locationLinkParams.location),
  };
  const selection = useSelectionScreen(fields, {
    warehouse: initial?.warehouse ?? linked.warehouse ?? defaultWarehouse ?? '',
    code: initial?.location ?? linked.location ?? '',
  });

  const warehouseCode = normalizeCode(parameterValue(selection.values, 'warehouse'));
  const warehouse = warehouses.find((candidate) => candidate.code === warehouseCode);
  const zones = useZones(warehouse?.id);
  const running = useRef(false);

  const execute = async () => {
    const values = selection.validate(show);

    if (values === undefined || running.current) {
      return;
    }

    const target = warehouses.find((candidate) => candidate.code === parameterValue(values, 'warehouse'));
    const code = parameterValue(values, 'code');

    if (target === undefined) {
      return;
    }

    running.current = true;

    try {
      const location = await findLocation(target.id, code);

      if (activity === 'create' && location !== undefined) {
        show({ type: 'E', text: t('messages.alreadyExists', { code, warehouse: target.code }), id: 'VALUE_ALREADY_EXISTS' });

        return;
      }

      if (activity !== 'create' && location === undefined) {
        show({ type: 'E', text: t('messages.notFound', { code, warehouse: target.code }), id: 'LOCATION_NOT_FOUND' });

        return;
      }

      onContinue({ warehouse: target, code, location });
    } catch (error) {
      show(errorMessage(error, t('messages.loadFailed')));
    } finally {
      running.current = false;
    }
  };

  useTransactionActions({
    execute: () => {
      void execute();
    },
  });

  // Abierta desde un enlace con depósito y ubicación: lee el registro sin esperar F8, una vez
  // que se conocen los depósitos, y quita los parámetros para que F3 vuelva a una pantalla
  // inicial normal.
  const linkPending = linked.warehouse !== null && linked.location !== null;
  const ready = !consult || visible.data !== undefined;

  useEffect(() => {
    if (linkPending && ready) {
      setSearchParams({}, { replace: true });
      void execute();
    }
    // Solo al abrir desde el enlace.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [linkPending, ready]);

  return (
    <WarehouseScopeProvider scope={{ warehouseId: warehouse?.id, zones: zones.data ?? [] }}>
      <div className="grid gap-3">
        <SelectionScreen selection={selection} title={t(`initial.${activity}`)} onMessage={show} />
        <p className="text-xs text-ink-muted">{t('initial.hint')}</p>
      </div>
    </WarehouseScopeProvider>
  );
}

// Ubicación con F4 de servidor como entrada de la pantalla de selección: sin tabla de
// verificación, porque F8 lee el registro e informa si no existe.
function LocationInput(props: SelectionInputProps) {
  return <LocationField {...props} />;
}
