import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useTransactionActions } from '@/app/shell/shellActions';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useInitialScreenFlow } from '@/app/shell/useInitialScreenFlow';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { useDefaultWarehouse } from './useDefaultWarehouse';
import { VisibleWarehouseField } from './WarehouseField';
import { useVisibleWarehouses } from './warehouseQueries';
import { WarehouseDetail } from './WarehousesTransaction';
import { normalizeCode, type Warehouse } from './warehousesApi';

// ZW11, ZW12 y ZW13: crear, modificar y visualizar un depósito, cada acción con su
// transacción (ADR-016), además de la gestión con lista y ficha de ZW01. Usan la misma ficha
// que ZW01. Crear abre la ficha directamente, como XK01: el código repetido se informa al
// grabar (VALUE_ALREADY_EXISTS).

export function CreateWarehouseTransaction() {
  // Cada grabación vuelve a una ficha vacía para el alta siguiente.
  const [round, setRound] = useState(0);
  const next = () => {
    setRound((current) => current + 1);
  };

  return <WarehouseDetail key={round} mode="create" warehouse={undefined} standalone onModeChange={next} onBack={next} />;
}

export function ChangeWarehouseTransaction() {
  return <WarehouseActivity activity="change" />;
}

export function DisplayWarehouseTransaction() {
  return <WarehouseActivity activity="display" />;
}

function WarehouseActivity({ activity }: { activity: 'change' | 'display' }) {
  const flow = useInitialScreenFlow((next: Warehouse) => next.code);

  if (flow.target === undefined) {
    return <WarehouseInitialScreen activity={activity} initial={flow.last} onContinue={flow.open} />;
  }

  return (
    <WarehouseDetail
      mode={activity}
      warehouse={flow.target}
      standalone
      // Grabar vuelve a la pantalla inicial con el depósito, listo para el siguiente.
      onModeChange={flow.close}
      onBack={flow.close}
    />
  );
}

interface WarehouseInitialScreenProps {
  activity: 'change' | 'display';
  initial: string | undefined;
  onContinue: (warehouse: Warehouse) => void;
}

// Pantalla inicial: el depósito, escrito o elegido con F4 entre los que el usuario consulta,
// activos o no. F8 abre la ficha.
function WarehouseInitialScreen({ activity, initial, onContinue }: WarehouseInitialScreenProps) {
  const { t } = useTranslation('warehouses');
  const { show } = useStatusBar();
  const visible = useVisibleWarehouses();
  const defaultWarehouse = useDefaultWarehouse();

  const fields = useMemo(
    (): SelectionField[] => [{ kind: 'parameter', id: 'warehouse', label: t('fields.code'), as: VisibleWarehouseField, required: true }],
    [t],
  );
  const selection = useSelectionScreen(fields, { warehouse: initial ?? defaultWarehouse ?? '' });

  useTransactionActions({
    execute: () => {
      const values = selection.validate(show);

      if (values === undefined) {
        return;
      }

      const code = normalizeCode(parameterValue(values, 'warehouse'));
      const warehouse = visible.data?.find((candidate) => candidate.code === code);

      // El campo ya informó el depósito inexistente o que no se puede consultar.
      if (warehouse !== undefined) {
        onContinue(warehouse);
      }
    },
  });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t(`initial.${activity}`)} onMessage={show} />
      <InfoNotice>{t('initial.hint')}</InfoNotice>
    </div>
  );
}
