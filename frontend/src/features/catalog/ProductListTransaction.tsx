import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { useTransactionActions } from '@/app/shell/shellActions';
import { useStatusBar } from '@/app/shell/statusMessages';
import type { ListFilterValue } from '@/shared/api/listing';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort, GridSource } from '@/shared/ui/grid/gridTypes';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue, selectionFilters } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps, SelectionValues } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { fetchProducts, normalizeSku, productsQueryKey, productStatuses, type Product } from './catalogApi';
import { CategoryField } from './CatalogFields';
import { categoryPaths, useCategories, useUnits } from './catalogQueries';
import { productLink } from './productLinks';
import { InfoNotice } from '@/shared/ui/InfoNotice';

const bySku: GridSort = { columnId: 'sku', desc: false };

// MM60: lista de productos (E2.13, D7). Pantalla de selección con SKU (valores, rangos, patrón
// con * y exclusiones), nombre por fragmento, código de barras exacto y categoría; con F8, la
// grilla en modo servidor, que filtra además por estado. Doble clic abre la visualización.
export function ProductListTransaction() {
  const { t } = useTranslation('products');
  const { show } = useStatusBar();
  const categories = useCategories();
  const [values, setValues] = useState<SelectionValues>();

  const fields = useMemo(
    (): SelectionField[] => [
      { kind: 'selectOptions', id: 'sku', label: t('fields.sku'), normalize: normalizeSku },
      { kind: 'parameter', id: 'name', label: t('fields.name') },
      { kind: 'parameter', id: 'barcode', label: t('fields.barcode') },
      { kind: 'parameter', id: 'category', label: t('fields.category'), as: CategoryInput },
    ],
    [t],
  );
  const selection = useSelectionScreen(fields, {});

  const execute = () => {
    const validated = selection.validate(show);

    if (validated !== undefined) {
      setValues(validated);
    }
  };

  const filters = useMemo((): Record<string, ListFilterValue> | undefined => {
    if (values === undefined) {
      return undefined;
    }

    const paths = categoryPaths(categories.data ?? []);
    const category = parameterValue(values, 'category');
    const categoryId = [...paths.entries()].find(([, path]) => path === category)?.[0];
    const name = parameterValue(values, 'name').trim();
    const barcode = parameterValue(values, 'barcode').trim();

    return {
      ...selectionFilters(fields, values),
      ...(name === '' ? {} : { name }),
      ...(barcode === '' ? {} : { barcode }),
      ...(categoryId === undefined ? {} : { category_id: String(categoryId) }),
    };
  }, [values, fields, categories.data]);

  if (filters === undefined) {
    return <ListSelection selection={selection} onExecute={execute} />;
  }

  return (
    <ProductResult
      filters={filters}
      onBack={() => {
        setValues(undefined);
      }}
    />
  );
}

function ListSelection({ selection, onExecute }: { selection: ReturnType<typeof useSelectionScreen>; onExecute: () => void }) {
  const { t } = useTranslation('products');
  const { show } = useStatusBar();

  useTransactionActions({ execute: onExecute });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t('list.selection')} onMessage={show} />
      <InfoNotice>{t('list.hint')}</InfoNotice>
    </div>
  );
}

function ProductResult({ filters, onBack }: { filters: Record<string, ListFilterValue>; onBack: () => void }) {
  const { t } = useTranslation('products');
  const { show } = useStatusBar();
  const navigate = useNavigate();
  const categories = useCategories();
  const units = useUnits();

  useTransactionActions({ back: onBack });

  const paths = useMemo(() => categoryPaths(categories.data ?? []), [categories.data]);
  const unitCodes = useMemo(() => new Map((units.data ?? []).map((unit) => [unit.id, unit.code])), [units.data]);

  const columns = useMemo(
    (): GridColumn<Product>[] => [
      // SKU, nombre, código de barras y categoría se acotan en la pantalla de selección.
      { id: 'sku', header: t('fields.sku'), value: (row) => row.sku, sortable: true, filter: false },
      { id: 'name', header: t('fields.name'), value: (row) => row.name, sortable: true, filter: false },
      { id: 'barcode', header: t('fields.barcode'), value: (row) => row.barcode, filter: false },
      { id: 'category', header: t('fields.category'), value: (row) => (row.category_id === null ? '' : (paths.get(row.category_id) ?? '')), filter: false },
      { id: 'base_uom', header: t('fields.baseUom'), value: (row) => unitCodes.get(row.base_uom_id) ?? '', filter: false },
      {
        id: 'status',
        header: t('fields.status'),
        value: (row) => row.status,
        format: (value) => t(`statuses.${String(value) as Product['status']}`),
        filter: 'in',
        options: productStatuses.map((status) => ({ value: status, label: t(`statuses.${status}`) })),
      },
      { id: 'weight_grams', header: t('fields.weightGrams'), value: (row) => row.weight_grams, type: 'number', filter: false },
    ],
    [t, paths, unitCodes],
  );

  const source = useMemo(
    (): GridSource<Product> => ({
      kind: 'server',
      queryKey: [...productsQueryKey, 'list', filters],
      fetchPage: (params, signal) => fetchProducts({ ...params, filters: { ...params.filters, ...filters } }, signal),
    }),
    [filters],
  );

  const exportName = 'productos';

  return (
    <DataGrid
      label={t('list.title')}
      columns={columns}
      source={source}
      rowKey={(row) => row.id}
      exportName={exportName}
      onMessage={show}
      onRowActivate={(row) => {
        void navigate(productLink('MM03', row.sku));
      }}
      initialSort={bySku}
    />
  );
}

// Categoría con F4 local como parámetro de la pantalla de selección, con su tabla de
// verificación: una categoría inexistente no se puede usar para acotar.
function CategoryInput(props: SelectionInputProps) {
  return <CategoryField {...props} />;
}
