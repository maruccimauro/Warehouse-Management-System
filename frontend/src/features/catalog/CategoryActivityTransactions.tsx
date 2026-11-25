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
import type { Category } from './catalogApi';
import { CategoryField } from './CatalogFields';
import { categoryPaths, savedCategoryPath, useCategories } from './catalogQueries';
import { CategoryDetail } from './CategoriesTransaction';

// ZW71, ZW72 y ZW73: crear, modificar y visualizar una categoría, cada acción con su
// transacción (ADR-016), además de la gestión de ZW07, con la misma ficha. Crear abre la
// ficha directamente, como ZW11. Modificar y visualizar piden la categoría por su ruta desde la
// raíz, que la identifica aunque otra rama tenga una del mismo nombre.

export function CreateCategoryTransaction() {
  // Cada grabación vuelve a una ficha vacía para el alta siguiente.
  const [round, setRound] = useState(0);
  const next = () => {
    setRound((current) => current + 1);
  };

  return <CategoryDetail key={round} mode="create" category={undefined} standalone onModeChange={next} onBack={next} />;
}

export function ChangeCategoryTransaction() {
  return <CategoryActivity activity="change" />;
}

export function DisplayCategoryTransaction() {
  return <CategoryActivity activity="display" />;
}

function CategoryActivity({ activity }: { activity: 'change' | 'display' }) {
  const categories = useCategories();
  const paths = useMemo(() => categoryPaths(categories.data ?? []), [categories.data]);
  const flow = useInitialScreenFlow((next: Category) => paths.get(next.id) ?? next.name);
  // Al grabar, la ruta puede cambiar (otro nombre u otra categoría superior): la pantalla
  // inicial vuelve con la ruta nueva y no con la ingresada.
  const [savedPath, setSavedPath] = useState<string>();

  if (flow.target === undefined) {
    return (
      <CategoryInitialScreen
        activity={activity}
        initial={savedPath ?? flow.last}
        onContinue={(category) => {
          setSavedPath(undefined);
          flow.open(category);
        }}
      />
    );
  }

  return (
    <CategoryDetail
      mode={activity}
      category={flow.target}
      standalone
      onModeChange={(_mode, saved) => {
        setSavedPath(savedCategoryPath(saved, paths));
        flow.close();
      }}
      onBack={flow.close}
    />
  );
}

interface CategoryInitialScreenProps {
  activity: 'change' | 'display';
  initial: string | undefined;
  onContinue: (category: Category) => void;
}

// Pantalla inicial: la categoría, escrita o elegida con F4. El campo verifica que exista.
function CategoryInitialScreen({ activity, initial, onContinue }: CategoryInitialScreenProps) {
  const { t } = useTranslation('categories');
  const { show } = useStatusBar();
  const categories = useCategories();

  const fields = useMemo(
    (): SelectionField[] => [{ kind: 'parameter', id: 'category', label: t('fields.path'), as: CategoryField, required: true }],
    [t],
  );
  const selection = useSelectionScreen(fields, { category: initial ?? '' });

  useTransactionActions({
    execute: () => {
      const values = selection.validate(show);

      if (values === undefined) {
        return;
      }

      const path = parameterValue(values, 'category');
      const paths = categoryPaths(categories.data ?? []);
      const category = categories.data?.find((candidate) => paths.get(candidate.id) === path);

      // El campo ya informó la categoría inexistente.
      if (category !== undefined) {
        onContinue(category);
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
