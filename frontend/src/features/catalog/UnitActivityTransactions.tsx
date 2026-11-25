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
import type { Unit } from './catalogApi';
import { UnitField } from './CatalogFields';
import { useUnits } from './catalogQueries';
import { UnitDetail } from './UnitsTransaction';

// ZW81, ZW82 y ZW83: crear, modificar y visualizar una unidad de medida, cada acción con su
// transacción (ADR-016), además de la gestión de ZW08, con la misma ficha. Crear abre la ficha
// directamente; el código repetido se informa al grabar (VALUE_ALREADY_EXISTS).

export function CreateUnitTransaction() {
  // Cada grabación vuelve a una ficha vacía para el alta siguiente.
  const [round, setRound] = useState(0);
  const next = () => {
    setRound((current) => current + 1);
  };

  return <UnitDetail key={round} mode="create" unit={undefined} standalone onModeChange={next} onBack={next} />;
}

export function ChangeUnitTransaction() {
  return <UnitActivity activity="change" />;
}

export function DisplayUnitTransaction() {
  return <UnitActivity activity="display" />;
}

function UnitActivity({ activity }: { activity: 'change' | 'display' }) {
  const flow = useInitialScreenFlow((next: Unit) => next.code);

  if (flow.target === undefined) {
    return <UnitInitialScreen activity={activity} initial={flow.last} onContinue={flow.open} />;
  }

  return (
    <UnitDetail
      mode={activity}
      unit={flow.target}
      standalone
      // Grabar vuelve a la pantalla inicial con la unidad, lista para la siguiente.
      onModeChange={flow.close}
      onBack={flow.close}
    />
  );
}

interface UnitInitialScreenProps {
  activity: 'change' | 'display';
  initial: string | undefined;
  onContinue: (unit: Unit) => void;
}

// Pantalla inicial: la unidad, escrita o elegida con F4. El campo verifica que exista.
function UnitInitialScreen({ activity, initial, onContinue }: UnitInitialScreenProps) {
  const { t } = useTranslation('units');
  const { show } = useStatusBar();
  const units = useUnits();

  const fields = useMemo(
    (): SelectionField[] => [{ kind: 'parameter', id: 'unit', label: t('fields.code'), as: UnitField, required: true }],
    [t],
  );
  const selection = useSelectionScreen(fields, { unit: initial ?? '' });

  useTransactionActions({
    execute: () => {
      const values = selection.validate(show);

      if (values === undefined) {
        return;
      }

      const code = parameterValue(values, 'unit').trim().toUpperCase();
      const unit = units.data?.find((candidate) => candidate.code === code);

      // El campo ya informó la unidad inexistente.
      if (unit !== undefined) {
        onContinue(unit);
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
