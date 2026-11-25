import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { requiredText } from '@/shared/ui/form/fieldRules';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort, GridSource } from '@/shared/ui/grid/gridTypes';
import { categoriesQueryKey, categoryNameMaxLength, createCategory, updateCategory, type Category } from './catalogApi';
import { CategoryField } from './CatalogFields';
import { categoryPaths, useCategories } from './catalogQueries';

type Screen = { kind: 'list' } | { kind: 'detail'; mode: 'create' | 'display' | 'change'; category?: Category };

type CategoryRow = Category & { path: string; parentPath: string };

const byPath: GridSort = { columnId: 'path', desc: false };
const exportName = 'categorias';

// ZW07: categorías del catálogo (E2.23). Lista con la ruta de cada categoría desde la raíz y,
// con doble clic, la ficha en modo visualizar, con Modificar y Crear en la barra de aplicación.
// Las categorías son pocas: la lista se lee entera y la grilla ordena y filtra en el cliente,
// porque la ruta la arma la pantalla. Profundidad y ciclos los valida el backend (REG-016).
export function CategoriesTransaction() {
  const [screen, setScreen] = useState<Screen>({ kind: 'list' });

  if (screen.kind === 'list') {
    return (
      <CategoryList
        onOpen={(category) => {
          setScreen({ kind: 'detail', mode: 'display', category });
        }}
        onCreate={() => {
          setScreen({ kind: 'detail', mode: 'create' });
        }}
      />
    );
  }

  return (
    <CategoryDetail
      key={`${screen.mode}-${String(screen.category?.id ?? 'new')}`}
      mode={screen.mode}
      category={screen.category}
      onModeChange={(mode, category) => {
        setScreen({ kind: 'detail', mode, category });
      }}
      onBack={() => {
        setScreen({ kind: 'list' });
      }}
    />
  );
}

function CategoryList({ onOpen, onCreate }: { onOpen: (category: Category) => void; onCreate: () => void }) {
  const { t } = useTranslation('categories');
  const { show } = useStatusBar();
  const categories = useCategories();

  const rows = useMemo((): CategoryRow[] => {
    const paths = categoryPaths(categories.data ?? []);

    return (categories.data ?? []).map((category) => ({
      ...category,
      path: paths.get(category.id) ?? category.name,
      parentPath: category.parent_id === null ? '' : (paths.get(category.parent_id) ?? ''),
    }));
  }, [categories.data]);

  const columns = useMemo(
    (): GridColumn<CategoryRow>[] => [
      { id: 'path', header: t('fields.path'), value: (row) => row.path, sortable: true, filter: 'contains' },
      { id: 'name', header: t('fields.name'), value: (row) => row.name, sortable: true, filter: 'contains' },
      { id: 'parent', header: t('fields.parent'), value: (row) => row.parentPath, sortable: true, filter: 'contains' },
    ],
    [t],
  );

  const source = useMemo((): GridSource<CategoryRow> => ({ kind: 'local', rows }), [rows]);
  const actions = [{ id: 'create', label: t('actions.create'), onClick: onCreate }];

  return (
    <div className="grid gap-3">
      <ApplicationToolbar label={t('list.actions')} actions={actions} />
      <DataGrid
        label={t('list.title')}
        columns={columns}
        source={source}
        rowKey={(row) => row.id}
        exportName={exportName}
        onMessage={show}
        onRowActivate={onOpen}
        initialSort={byPath}
      />
    </div>
  );
}

const categorySchema = z.object({
  name: requiredText(categoryNameMaxLength),
  parent: z.string(),
});

interface CategoryDetailProps {
  mode: 'create' | 'display' | 'change';
  category: Category | undefined;
  // Transacción individual (crear, modificar o visualizar): sin el paso de visualizar a
  // modificar, que es de la transacción de gestión.
  standalone?: boolean;
  onModeChange: (mode: 'display' | 'change', category: Category) => void;
  onBack: () => void;
}

// La ficha espera las categorías: sin ellas no hay ruta de la categoría superior, y los valores
// iniciales del formulario no cambian después de montarlo.
export function CategoryDetail(props: CategoryDetailProps) {
  const { t } = useTranslation('categories');
  const categories = useCategories();

  if (categories.isError) {
    return <p className="text-error">{errorMessage(categories.error, t('messages.loadFailed')).text}</p>;
  }

  if (categories.data === undefined) {
    return <p className="text-ink-muted">{t('detail.loading')}</p>;
  }

  return <CategoryForm {...props} categories={categories.data} />;
}

function CategoryForm({
  mode,
  category,
  standalone = false,
  onModeChange,
  onBack,
  categories,
}: CategoryDetailProps & { categories: readonly Category[] }) {
  const { t } = useTranslation('categories');
  const queryClient = useQueryClient();
  const paths = useMemo(() => categoryPaths(categories), [categories]);

  const defaultValues = useMemo(
    () => ({
      name: category?.name ?? '',
      parent: category?.parent_id == null ? '' : (paths.get(category.parent_id) ?? ''),
    }),
    [category, paths],
  );

  const parentIdOf = (path: string): number | null =>
    path === '' ? null : ([...paths.entries()].find(([, candidate]) => candidate === path)?.[0] ?? null);

  const { Form, Section, Field } = useTransactionForm({
    schema: categorySchema,
    defaultValues,
    namespace: 'categories',
    readOnly: mode === 'display',
    savedMessage: mode === 'create' ? t('messages.created') : t('messages.saved'),
    saveFailedMessage: t('messages.saveFailed'),
    serverFields: { parent_id: 'parent' },
    onBack,
    onSave: async ({ name, parent }) => {
      const input = { name, parent_id: parentIdOf(parent) };
      const saved = mode === 'create' ? await createCategory(input) : await updateCategory(category?.id ?? 0, input);

      await queryClient.invalidateQueries({ queryKey: categoriesQueryKey });
      onModeChange('display', saved);
    },
  });

  const actions: ApplicationAction[] =
    mode === 'display' && !standalone && category !== undefined
      ? [
          {
            id: 'change',
            label: t('actions.change'),
            primary: true,
            onClick: () => {
              onModeChange('change', category);
            },
          },
        ]
      : [];

  return (
    <div className="grid gap-3">
      {category !== undefined && <p className="font-medium">{t('detail.path', { path: paths.get(category.id) ?? category.name })}</p>}
      <ApplicationToolbar label={t('detail.actions')} actions={actions} />
      <Form>
        <Section name="general">
          <Field name="name" required />
          <Field name="parent" as={CategoryField} className="w-96" description={mode === 'display' ? undefined : t('detail.parentHint')} />
        </Section>
      </Form>
    </div>
  );
}
