import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useScreenContext } from '@/app/shell/useScreenContext';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { codeRule, requiredText } from '@/shared/ui/form/fieldRules';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort, GridSource } from '@/shared/ui/grid/gridTypes';
import { createUnit, fetchUnits, unitCodeMaxLength, unitNameMaxLength, unitsQueryKey, updateUnit, type Unit } from './catalogApi';

type Screen = { kind: 'list' } | { kind: 'detail'; mode: 'create' | 'display' | 'change'; unit?: Unit };

const unitSource: GridSource<Unit> = { kind: 'server', queryKey: [...unitsQueryKey, 'grid'], fetchPage: fetchUnits };
const byCode: GridSort = { columnId: 'code', desc: false };
const exportName = 'unidades';

// ZW08: unidades de medida (E2.23). Lista y ficha como ZW01. El código no cambia después del
// alta: las cantidades de los artículos que la usan como unidad base están expresadas en ella.
export function UnitsTransaction() {
  const [screen, setScreen] = useState<Screen>({ kind: 'list' });
  useScreenContext(screen.kind);

  if (screen.kind === 'list') {
    return (
      <UnitList
        onOpen={(unit) => {
          setScreen({ kind: 'detail', mode: 'display', unit });
        }}
        onCreate={() => {
          setScreen({ kind: 'detail', mode: 'create' });
        }}
      />
    );
  }

  return (
    <UnitDetail
      key={`${screen.mode}-${String(screen.unit?.id ?? 'new')}`}
      mode={screen.mode}
      unit={screen.unit}
      onModeChange={(mode, unit) => {
        setScreen({ kind: 'detail', mode, unit });
      }}
      onBack={() => {
        setScreen({ kind: 'list' });
      }}
    />
  );
}

function UnitList({ onOpen, onCreate }: { onOpen: (unit: Unit) => void; onCreate: () => void }) {
  const { t } = useTranslation('units');
  const { show } = useStatusBar();

  const columns = useMemo(
    (): GridColumn<Unit>[] => [
      { id: 'code', header: t('fields.code'), value: (row) => row.code, sortable: true, filter: 'contains' },
      { id: 'name', header: t('fields.name'), value: (row) => row.name, sortable: true, filter: 'contains' },
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
        source={unitSource}
        rowKey={(row) => row.id}
        exportName={exportName}
        onMessage={show}
        onRowActivate={onOpen}
        initialSort={byCode}
      />
    </div>
  );
}

const unitSchema = z.object({
  code: codeRule(unitCodeMaxLength),
  name: requiredText(unitNameMaxLength),
});

interface UnitDetailProps {
  mode: 'create' | 'display' | 'change';
  unit: Unit | undefined;
  // Transacción individual (crear, modificar o visualizar): sin el paso de visualizar a
  // modificar, que es de la transacción de gestión.
  standalone?: boolean;
  onModeChange: (mode: 'display' | 'change', unit: Unit) => void;
  onBack: () => void;
}

export function UnitDetail({ mode, unit, standalone = false, onModeChange, onBack }: UnitDetailProps) {
  const { t } = useTranslation('units');
  const queryClient = useQueryClient();

  const defaultValues = useMemo(() => ({ code: unit?.code ?? '', name: unit?.name ?? '' }), [unit]);

  const { Form, Section, Field } = useTransactionForm({
    schema: unitSchema,
    defaultValues,
    namespace: 'units',
    readOnly: mode === 'display',
    savedMessage: mode === 'create' ? t('messages.created') : t('messages.saved'),
    saveFailedMessage: t('messages.saveFailed'),
    onBack,
    onSave: async ({ code, name }) => {
      const saved = mode === 'create' ? await createUnit({ code: code.trim().toUpperCase(), name }) : await updateUnit(unit?.id ?? 0, { name });

      // La lista, la ayuda F4 de la unidad base y la descripción en las fichas de artículo.
      void queryClient.invalidateQueries({ queryKey: unitsQueryKey });
      onModeChange('display', saved);
    },
  });

  const actions: ApplicationAction[] =
    mode === 'display' && !standalone && unit !== undefined
      ? [
          {
            id: 'change',
            label: t('actions.change'),
            primary: true,
            onClick: () => {
              onModeChange('change', unit);
            },
          },
        ]
      : [];

  return (
    <div className="grid gap-3">
      <ApplicationToolbar label={t('detail.actions')} actions={actions} />
      <Form>
        <Section name="general">
          <Field name="code" required readOnly={mode !== 'create'} className="w-32 uppercase" />
          <Field name="name" required />
        </Section>
      </Form>
    </div>
  );
}
