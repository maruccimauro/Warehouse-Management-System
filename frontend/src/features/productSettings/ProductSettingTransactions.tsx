import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useInitialScreenFlow } from '@/app/shell/useInitialScreenFlow';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import type { WarehouseSummary } from '@/features/auth/authApi';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import {
  fetchWarehouseSettings,
  findProduct,
  maxQuantity,
  normalizeSku,
  productDetailQueryKey,
  productStatuses,
} from '@/features/catalog/catalogApi';
import { ProductField } from '@/features/catalog/CatalogFields';
import { useDefaultWarehouse } from '@/features/warehouses/useDefaultWarehouse';
import { VisibleWarehouseField, WarehouseField } from '@/features/warehouses/WarehouseField';
import { useVisibleWarehouses } from '@/features/warehouses/warehouseQueries';
import { normalizeCode } from '@/features/warehouses/warehousesApi';
import type { ListFilterValue } from '@/shared/api/listing';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { nonNegativeInteger, optionalPositiveInteger } from '@/shared/ui/form/fieldRules';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort, GridSource } from '@/shared/ui/grid/gridTypes';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue, selectionFilters } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps, SelectionValues } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import {
  fetchWarehouseProductSettings,
  productSettingsQueryKey,
  setAdjustmentThresholds,
  setMinStock,
  warehouseProductSettingsQueryKey,
  type WarehouseProductSetting,
} from './productSettingsApi';

// Datos de un artículo en un depósito (DM-21), separados del maestro global del catálogo
// (MM01 a MM03) porque tienen otro responsable (.claude/rules/responsabilidades-por-transaccion.md):
//   ZW04, ZW42 y ZW43: gestionar, modificar y visualizar el stock mínimo. Lo mantiene el
//     supervisor en los depósitos donde opera (catalog.min_stock.manage).
//   ZW35, ZW36 y ZW37: gestionar, modificar y visualizar los umbrales de aprobación de ajustes.
//     Son un control central (inventory.thresholds.manage): basta con ver el depósito.
// No hay Crear: un artículo sin fila en el depósito ya tiene sus valores por defecto (stock
// mínimo 0, sin umbrales), y la gestión lista todos los artículos del catálogo con su valor.
export type SettingKind = 'minStock' | 'thresholds';
type Mode = 'display' | 'change';

const bySku: GridSort = { columnId: 'sku', desc: false };

// Nombre del archivo exportado, seguido del código del depósito.
const exportPrefix: Record<SettingKind, string> = { minStock: 'stock-minimo', thresholds: 'umbrales-de-ajuste' };

export function MinStockManagementTransaction() {
  return <SettingManagement kind="minStock" />;
}

export function ChangeMinStockTransaction() {
  return <SettingActivity kind="minStock" activity="change" />;
}

export function DisplayMinStockTransaction() {
  return <SettingActivity kind="minStock" activity="display" />;
}

export function AdjustmentThresholdsManagementTransaction() {
  return <SettingManagement kind="thresholds" />;
}

export function ChangeAdjustmentThresholdsTransaction() {
  return <SettingActivity kind="thresholds" activity="change" />;
}

export function DisplayAdjustmentThresholdsTransaction() {
  return <SettingActivity kind="thresholds" activity="display" />;
}

// Depósitos que admite la pantalla: modificar el stock mínimo exige operar en el depósito
// (REG-005); visualizarlo y todo lo de los umbrales, que son un control central, solo verlo.
function useWarehouseScope(kind: SettingKind, mode: Mode) {
  const user = useAuthenticatedUser();
  const visible = useVisibleWarehouses();
  const operate = kind === 'minStock' && mode === 'change';

  return {
    field: operate ? WarehouseField : VisibleWarehouseField,
    warehouses: operate ? user.warehouses : (visible.data ?? []),
  };
}

function findWarehouse(warehouses: readonly WarehouseSummary[], values: SelectionValues): WarehouseSummary | undefined {
  const code = normalizeCode(parameterValue(values, 'warehouse'));

  return warehouses.find((candidate) => candidate.code === code);
}

type ManagementScreen =
  | { kind: 'selection' }
  | { kind: 'list'; warehouse: WarehouseSummary; filters: Record<string, ListFilterValue> }
  | { kind: 'detail'; warehouse: WarehouseSummary; filters: Record<string, ListFilterValue>; mode: Mode; row: WarehouseProductSetting };

// Gestión (ZW04, ZW35): pantalla de selección con el depósito y el SKU (F8), la lista de los
// artículos con su valor en el depósito y la ficha, en visualizar con Modificar en la barra.
function SettingManagement({ kind }: { kind: SettingKind }) {
  const { t } = useTranslation('productSettings');
  const { show } = useStatusBar();
  const defaultWarehouse = useDefaultWarehouse();
  const scope = useWarehouseScope(kind, 'change');
  const [screen, setScreen] = useState<ManagementScreen>({ kind: 'selection' });

  const fields = useMemo(
    (): SelectionField[] => [
      { kind: 'parameter', id: 'warehouse', label: t('fields.warehouse'), as: scope.field, required: true },
      { kind: 'selectOptions', id: 'sku', label: t('fields.sku'), normalize: normalizeSku },
    ],
    [t, scope.field],
  );
  const selection = useSelectionScreen(fields, { warehouse: defaultWarehouse ?? '' });

  const execute = () => {
    const values = selection.validate(show);
    // El campo ya informó un depósito inexistente o fuera del alcance.
    const warehouse = values === undefined ? undefined : findWarehouse(scope.warehouses, values);

    if (values !== undefined && warehouse !== undefined) {
      setScreen({ kind: 'list', warehouse, filters: selectionFilters(fields, values) });
    }
  };

  if (screen.kind === 'selection') {
    return <ManagementSelection kind={kind} selection={selection} onExecute={execute} />;
  }

  if (screen.kind === 'list') {
    return (
      <SettingList
        kind={kind}
        warehouse={screen.warehouse}
        filters={screen.filters}
        onBack={() => {
          setScreen({ kind: 'selection' });
        }}
        onOpen={(row) => {
          setScreen({ ...screen, kind: 'detail', mode: 'display', row });
        }}
      />
    );
  }

  return (
    <SettingDetail
      key={`${screen.mode}-${String(screen.row.product_id)}`}
      kind={kind}
      warehouse={screen.warehouse}
      mode={screen.mode}
      row={screen.row}
      onModeChange={(mode, row) => {
        setScreen({ ...screen, mode, row });
      }}
      onBack={() => {
        setScreen({ kind: 'list', warehouse: screen.warehouse, filters: screen.filters });
      }}
    />
  );
}

interface ManagementSelectionProps {
  kind: SettingKind;
  selection: ReturnType<typeof useSelectionScreen>;
  onExecute: () => void;
}

function ManagementSelection({ kind, selection, onExecute }: ManagementSelectionProps) {
  const { t } = useTranslation('productSettings');
  const { show } = useStatusBar();

  useTransactionActions({ execute: onExecute });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t(`${kind}.title`)} onMessage={show} />
      <InfoNotice>{t(`${kind}.selectionHint`)}</InfoNotice>
    </div>
  );
}

interface SettingListProps {
  kind: SettingKind;
  warehouse: WarehouseSummary;
  filters: Record<string, ListFilterValue>;
  onBack: () => void;
  onOpen: (row: WarehouseProductSetting) => void;
}

function SettingList({ kind, warehouse, filters, onBack, onOpen }: SettingListProps) {
  const { t } = useTranslation('productSettings');
  const { t: tProducts } = useTranslation('products');
  const { show } = useStatusBar();

  useTransactionActions({ back: onBack });

  const columns = useMemo((): GridColumn<WarehouseProductSetting>[] => {
    const product: GridColumn<WarehouseProductSetting>[] = [
      // El SKU se acota en la pantalla de selección; la lista filtra por nombre y estado.
      { id: 'sku', header: t('fields.sku'), value: (row) => row.sku, sortable: true, filter: false },
      { id: 'name', header: t('fields.productName'), value: (row) => row.name, sortable: true, filter: 'contains' },
      {
        id: 'status',
        header: t('fields.productStatus'),
        value: (row) => row.status,
        format: (value) => tProducts(`statuses.${String(value) as WarehouseProductSetting['status']}`),
        filter: 'in',
        options: productStatuses.map((status) => ({ value: status, label: tProducts(`statuses.${status}`) })),
      },
    ];

    return kind === 'minStock'
      ? [...product, { id: 'minStock', header: t('fields.minStock'), value: (row) => row.min_stock, type: 'number', filter: false }]
      : [
          ...product,
          {
            id: 'adjustmentInThreshold',
            header: t('fields.adjustmentInThreshold'),
            value: (row) => row.adjustment_in_approval_threshold,
            type: 'number',
            filter: false,
          },
          {
            id: 'adjustmentOutThreshold',
            header: t('fields.adjustmentOutThreshold'),
            value: (row) => row.adjustment_out_approval_threshold,
            type: 'number',
            filter: false,
          },
        ];
  }, [t, tProducts, kind]);

  const source = useMemo(
    (): GridSource<WarehouseProductSetting> => ({
      kind: 'server',
      queryKey: [...warehouseProductSettingsQueryKey(warehouse.id), 'grid', filters],
      fetchPage: (params, signal) => fetchWarehouseProductSettings(warehouse.id, { ...params, filters: { ...params.filters, ...filters } }, signal),
    }),
    [warehouse.id, filters],
  );

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('warehouse', { code: warehouse.code, name: warehouse.name })}</p>
      <DataGrid
        label={t(`${kind}.listTitle`, { code: warehouse.code })}
        columns={columns}
        source={source}
        rowKey={(row) => row.product_id}
        exportName={`${exportPrefix[kind]}-${warehouse.code}`}
        onMessage={show}
        onRowActivate={onOpen}
        initialSort={bySku}
      />
      <InfoNotice>{t(`${kind}.listHint`)}</InfoNotice>
    </div>
  );
}

interface SettingTarget {
  warehouse: WarehouseSummary;
  row: WarehouseProductSetting;
}

interface SettingEntry {
  warehouse: string;
  product: string;
}

// Modificar y visualizar (ZW42 y ZW43, ZW36 y ZW37): pantalla inicial con el depósito y el
// artículo (F8) y la ficha; grabar o F3 vuelven a la pantalla inicial.
function SettingActivity({ kind, activity }: { kind: SettingKind; activity: Mode }) {
  const flow = useInitialScreenFlow((next: SettingTarget): SettingEntry => ({ warehouse: next.warehouse.code, product: next.row.sku }));

  if (flow.target === undefined) {
    return <SettingInitialScreen kind={kind} activity={activity} initial={flow.last} onContinue={flow.open} />;
  }

  return (
    <SettingDetail
      kind={kind}
      warehouse={flow.target.warehouse}
      mode={activity}
      row={flow.target.row}
      standalone
      onModeChange={flow.close}
      onBack={flow.close}
    />
  );
}

interface SettingInitialScreenProps {
  kind: SettingKind;
  activity: Mode;
  initial: SettingEntry | undefined;
  onContinue: (target: SettingTarget) => void;
}

function SettingInitialScreen({ kind, activity, initial, onContinue }: SettingInitialScreenProps) {
  const { t } = useTranslation('productSettings');
  const { show } = useStatusBar();
  const defaultWarehouse = useDefaultWarehouse();
  const scope = useWarehouseScope(kind, activity);
  const running = useRef(false);

  const fields = useMemo(
    (): SelectionField[] => [
      { kind: 'parameter', id: 'warehouse', label: t('fields.warehouse'), as: scope.field, required: true },
      { kind: 'parameter', id: 'product', label: t('fields.product'), as: ProductInput, required: true },
    ],
    [t, scope.field],
  );
  const selection = useSelectionScreen(fields, { warehouse: initial?.warehouse ?? defaultWarehouse ?? '', product: initial?.product ?? '' });

  const execute = async () => {
    const values = selection.validate(show);
    const warehouse = values === undefined ? undefined : findWarehouse(scope.warehouses, values);

    if (values === undefined || warehouse === undefined || running.current) {
      return;
    }

    const sku = normalizeSku(parameterValue(values, 'product'));
    running.current = true;

    try {
      const product = await findProduct(sku);

      if (product === undefined) {
        show({ type: 'E', text: t('messages.productNotFound', { sku }), id: 'PRODUCT_NOT_FOUND' });

        return;
      }

      // Sin fila en el depósito, el artículo tiene los valores por defecto.
      const setting = (await fetchWarehouseSettings(product.id)).find((candidate) => candidate.warehouse_id === warehouse.id);

      onContinue({
        warehouse,
        row: {
          product_id: product.id,
          sku: product.sku,
          name: product.name,
          status: product.status,
          warehouse_id: warehouse.id,
          min_stock: setting?.min_stock ?? 0,
          adjustment_in_approval_threshold: setting?.adjustment_in_approval_threshold ?? null,
          adjustment_out_approval_threshold: setting?.adjustment_out_approval_threshold ?? null,
        },
      });
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

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t(`${kind}.${activity}`)} onMessage={show} />
      <InfoNotice>{t('initialHint')}</InfoNotice>
    </div>
  );
}

// Los tres campos están en el esquema; cada ficha muestra y graba solo los suyos.
const settingSchema = z.object({
  product: z.string(),
  minStock: nonNegativeInteger(maxQuantity),
  adjustmentInThreshold: optionalPositiveInteger(maxQuantity),
  adjustmentOutThreshold: optionalPositiveInteger(maxQuantity),
});

const optionalNumber = (text: string) => (text.trim() === '' ? null : Number(text));
const numberText = (value: number | null) => (value === null ? '' : String(value));

interface SettingDetailProps {
  kind: SettingKind;
  warehouse: WarehouseSummary;
  mode: Mode;
  row: WarehouseProductSetting;
  // Transacción individual: sin el paso de visualizar a modificar, que es de la gestión.
  standalone?: boolean;
  onModeChange: (mode: Mode, row: WarehouseProductSetting) => void;
  onBack: () => void;
}

function SettingDetail({ kind, warehouse, mode, row, standalone = false, onModeChange, onBack }: SettingDetailProps) {
  const { t } = useTranslation('productSettings');
  const queryClient = useQueryClient();

  const defaultValues = useMemo(
    () => ({
      product: row.sku,
      minStock: String(row.min_stock),
      adjustmentInThreshold: numberText(row.adjustment_in_approval_threshold),
      adjustmentOutThreshold: numberText(row.adjustment_out_approval_threshold),
    }),
    [row],
  );

  const { Form, Section, Field } = useTransactionForm({
    schema: settingSchema,
    defaultValues,
    namespace: 'productSettings',
    readOnly: mode === 'display',
    savedMessage: t(`${kind}.saved`, { sku: row.sku, code: warehouse.code }),
    saveFailedMessage: t(`${kind}.saveFailed`),
    serverFields: {
      min_stock: 'minStock',
      adjustment_in_approval_threshold: 'adjustmentInThreshold',
      adjustment_out_approval_threshold: 'adjustmentOutThreshold',
    },
    onBack,
    onSave: async (form) => {
      const saved =
        kind === 'minStock'
          ? await setMinStock(row.product_id, warehouse.id, Number(form.minStock))
          : await setAdjustmentThresholds(row.product_id, warehouse.id, {
              adjustment_in_approval_threshold: optionalNumber(form.adjustmentInThreshold),
              adjustment_out_approval_threshold: optionalNumber(form.adjustmentOutThreshold),
            });

      // En segundo plano: el mensaje y el cambio de pantalla no esperan a la lista.
      void queryClient.invalidateQueries({ queryKey: productSettingsQueryKey });
      void queryClient.invalidateQueries({ queryKey: productDetailQueryKey(row.product_id) });
      onModeChange('display', {
        ...row,
        min_stock: saved.min_stock,
        adjustment_in_approval_threshold: saved.adjustment_in_approval_threshold,
        adjustment_out_approval_threshold: saved.adjustment_out_approval_threshold,
      });

      return undefined;
    },
  });

  const actions: ApplicationAction[] =
    mode === 'display' && !standalone
      ? [
          {
            id: 'change',
            label: t('change'),
            primary: true,
            onClick: () => {
              onModeChange('change', row);
            },
          },
        ]
      : [];
  const hint = mode === 'display' ? undefined : t(`${kind}.hint`, { code: warehouse.code });

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('warehouse', { code: warehouse.code, name: warehouse.name })}</p>
      <ApplicationToolbar label={t('detailActions')} actions={actions} />
      <Form>
        <Section name={kind}>
          <Field name="product" readOnly className="w-64" description={row.name} />
          {kind === 'minStock' ? (
            <Field name="minStock" inputMode="numeric" required className="w-40" description={hint} />
          ) : (
            <>
              <Field name="adjustmentInThreshold" inputMode="numeric" className="w-40" description={hint} />
              <Field name="adjustmentOutThreshold" inputMode="numeric" className="w-40" description={hint} />
            </>
          )}
        </Section>
      </Form>
      {mode === 'change' && <InfoNotice>{t('saveHint')}</InfoNotice>}
    </div>
  );
}

// Artículo con F4 de servidor, sin tabla de verificación: F8 lo lee e informa si no existe.
function ProductInput(props: SelectionInputProps) {
  return <ProductField {...props} />;
}
