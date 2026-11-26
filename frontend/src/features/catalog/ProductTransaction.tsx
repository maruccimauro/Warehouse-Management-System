import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo } from 'react';
import { useInitialScreenFlow } from '@/app/shell/useInitialScreenFlow';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { HelpLink } from '@/shared/help/HelpLink';
import { optionalPositiveInteger, optionalPrintable, required, requiredText, textRule } from '@/shared/ui/form/fieldRules';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn } from '@/shared/ui/grid/gridTypes';
import {
  barcodeMaxLength,
  createProduct,
  descriptionMaxLength,
  dimensionMax,
  fetchProductSuppliers,
  productDetailQueryKey,
  productNameMaxLength,
  productsQueryKey,
  productStatuses,
  updateProduct,
  type Category,
  type ProductInput,
  type ProductSupplier,
  type Unit,
} from './catalogApi';
import { CategoryField, UnitField } from './CatalogFields';
import { categoryPaths, useCategories, useUnits } from './catalogQueries';
import { ProductInitialScreen, type ProductActivity, type ProductEntry, type ProductTarget } from './ProductInitialScreen';
import { InfoNotice } from '@/shared/ui/InfoNotice';

// MM01, MM02 y MM03: crear, modificar y visualizar un artículo del catálogo (E2.13). La misma
// pantalla en tres modos, como LS01N a LS03N: inicial con el SKU (F8) y la ficha con los datos
// generales, que son globales. Los datos del artículo en un depósito tienen sus transacciones,
// con otro responsable: el stock mínimo (ZW04) y los umbrales de ajuste (ZW35). Al grabar
// vuelve a la pantalla inicial con lo ingresado.
export function ProductTransaction({ activity }: { activity: ProductActivity }) {
  const flow = useInitialScreenFlow((next: ProductTarget): ProductEntry => ({ sku: next.sku }));

  if (flow.target === undefined) {
    return <ProductInitialScreen activity={activity} initial={flow.last} onContinue={flow.open} />;
  }

  return <ProductDetail activity={activity} target={flow.target} onDone={flow.close} />;
}

export function CreateProductTransaction() {
  return <ProductTransaction activity="create" />;
}

export function ChangeProductTransaction() {
  return <ProductTransaction activity="change" />;
}

export function DisplayProductTransaction() {
  return <ProductTransaction activity="display" />;
}

const productFormSchema = z.object({
  sku: z.string(),
  name: requiredText(productNameMaxLength),
  description: textRule(descriptionMaxLength),
  barcode: optionalPrintable(barcodeMaxLength),
  // Categoría y unidad las valida su campo contra las filas (tabla de verificación).
  category: z.string(),
  baseUom: required(),
  status: z.enum(productStatuses),
  weightGrams: optionalPositiveInteger(dimensionMax),
  lengthMm: optionalPositiveInteger(dimensionMax),
  widthMm: optionalPositiveInteger(dimensionMax),
  heightMm: optionalPositiveInteger(dimensionMax),
});

type ProductFormValues = z.input<typeof productFormSchema>;

const nullable = (text: string) => (text.trim() === '' ? null : text.trim());
const optionalNumber = (text: string) => (text.trim() === '' ? null : Number(text));
const numberText = (value: number | null | undefined) => (value == null ? '' : String(value));

interface ProductDetailProps {
  activity: ProductActivity;
  target: ProductTarget;
  onDone: () => void;
}

// Lee lo que la ficha necesita antes de montar el formulario (categorías y unidades), para que
// los valores por defecto no cambien después.
function ProductDetail({ activity, target, onDone }: ProductDetailProps) {
  const { t } = useTranslation('products');
  const categories = useCategories();
  const units = useUnits();

  if (categories.isError || units.isError) {
    return <p className="text-error">{errorMessage(categories.error ?? units.error, t('messages.loadFailed')).text}</p>;
  }

  if (categories.data === undefined || units.data === undefined) {
    return <p className="text-ink-muted">{t('detail.loading')}</p>;
  }

  return (
    <>
      <ProductForm activity={activity} target={target} categories={categories.data} units={units.data} onDone={onDone} />
      {activity === 'display' && target.product !== undefined && <ProductSuppliers productId={target.product.id} />}
    </>
  );
}

interface ProductFormProps {
  activity: ProductActivity;
  target: ProductTarget;
  categories: readonly Category[];
  units: readonly Unit[];
  onDone: () => void;
}

function ProductForm({ activity, target, categories, units, onDone }: ProductFormProps) {
  const { t } = useTranslation('products');
  const queryClient = useQueryClient();
  const { product, sku } = target;
  const paths = useMemo(() => categoryPaths(categories), [categories]);

  const defaultValues = useMemo(
    (): ProductFormValues => ({
      sku,
      name: product?.name ?? '',
      description: product?.description ?? '',
      barcode: product?.barcode ?? '',
      category: product?.category_id == null ? '' : (paths.get(product.category_id) ?? ''),
      baseUom: units.find((unit) => unit.id === product?.base_uom_id)?.code ?? '',
      status: product?.status ?? 'active',
      weightGrams: numberText(product?.weight_grams),
      lengthMm: numberText(product?.length_mm),
      widthMm: numberText(product?.width_mm),
      heightMm: numberText(product?.height_mm),
    }),
    [sku, product, paths, units],
  );

  const categoryIdOf = (path: string) => [...paths.entries()].find(([, candidate]) => candidate === path)?.[0];

  const { Form, Section, Field, values } = useTransactionForm({
    schema: productFormSchema,
    defaultValues,
    namespace: 'products',
    readOnly: activity === 'display',
    savedMessage: activity === 'create' ? t('messages.created', { sku }) : t('messages.saved', { sku }),
    saveFailedMessage: t('messages.saveFailed'),
    serverFields: {
      category_id: 'category',
      base_uom_id: 'baseUom',
      weight_grams: 'weightGrams',
      length_mm: 'lengthMm',
      width_mm: 'widthMm',
      height_mm: 'heightMm',
    },
    onBack: onDone,
    onSave: async (form) => {
      const input: ProductInput = {
        name: form.name,
        description: nullable(form.description),
        barcode: nullable(form.barcode),
        category_id: form.category === '' ? null : (categoryIdOf(form.category) ?? null),
        weight_grams: optionalNumber(form.weightGrams),
        length_mm: optionalNumber(form.lengthMm),
        width_mm: optionalNumber(form.widthMm),
        height_mm: optionalNumber(form.heightMm),
      };

      const saved =
        activity === 'create'
          ? await createProduct({
              ...input,
              sku,
              name: form.name,
              base_uom_id: units.find((unit) => unit.code === form.baseUom)?.id ?? 0,
            })
          : await updateProduct(product?.id ?? 0, { ...input, status: form.status });

      void queryClient.invalidateQueries({ queryKey: productsQueryKey });
      void queryClient.invalidateQueries({ queryKey: productDetailQueryKey(saved.id) });
      onDone();

      return undefined;
    },
  });

  const statusOptions = productStatuses.map((status) => ({ value: status, label: t(`statuses.${status}`) }));
  const fixed = t('detail.fixedAfterCreate');

  return (
    <div className="grid gap-3">
      <Form>
        <Section name="general">
          <Field name="sku" readOnly className="w-64" description={activity === 'create' ? fixed : undefined} />
          <Field name="name" required />
          <Field name="description" rows={3} />
          {activity !== 'create' && <Field name="status" options={statusOptions} />}
          <Field name="barcode" className="w-64" />
          <Field name="category" as={CategoryField} />
          <Field
            name="baseUom"
            as={UnitField}
            required
            readOnly={activity !== 'create'}
            description={activity === 'create' ? fixed : undefined}
          />
        </Section>
        <Section name="dimensions">
          <Field name="weightGrams" inputMode="numeric" className="w-32" />
          <Field name="lengthMm" inputMode="numeric" className="w-32" />
          <Field name="widthMm" inputMode="numeric" className="w-32" />
          <Field name="heightMm" inputMode="numeric" className="w-32" />
        </Section>
      </Form>
      {product !== undefined && (
        <section className="grid max-w-4xl gap-1 text-ink-muted">
          <p>
            {t('fields.status')}:{' '}
            <HelpLink helpKey={`state.product.${values.status}`}>{t(`statuses.${values.status}`)}</HelpLink>
          </p>
          <p>{t('detail.lotTracked', { value: t(product.is_lot_tracked ? 'detail.yes' : 'detail.no') })}</p>
        </section>
      )}
      {activity !== 'display' && <InfoNotice>{t('detail.saveHint')}</InfoNotice>}
    </div>
  );
}

// Proveedores vinculados al producto, de solo lectura en la visualización (D5): se mantienen
// desde los productos del proveedor.
function ProductSuppliers({ productId }: { productId: number }) {
  const { t } = useTranslation('products');
  const { t: tSuppliers } = useTranslation('suppliers');
  const { show } = useStatusBar();
  const links = useQuery({
    queryKey: [...productDetailQueryKey(productId), 'suppliers'],
    queryFn: ({ signal }) => fetchProductSuppliers(productId, signal),
  });

  const columns = useMemo(
    (): GridColumn<ProductSupplier>[] => [
      { id: 'number', header: t('detail.supplierNumber'), value: (row) => row.supplier?.number ?? '' },
      { id: 'tax_id', header: t('detail.supplierTaxId'), value: (row) => row.supplier?.tax_id ?? '' },
      { id: 'name', header: t('detail.supplierName'), value: (row) => row.supplier?.name ?? '' },
      {
        id: 'is_active',
        header: t('detail.supplierActive'),
        value: (row) => (row.supplier?.is_active === false ? 'inactive' : 'active'),
        format: (value) => tSuppliers(`statuses.${String(value) as 'active' | 'inactive'}`),
      },
      { id: 'supplier_sku', header: t('detail.supplierSku'), value: (row) => row.supplier_sku },
      { id: 'lead_time_days', header: t('detail.leadTimeDays'), value: (row) => row.lead_time_days, type: 'number' },
    ],
    [t, tSuppliers],
  );

  const exportName = 'proveedores-del-producto';

  if (links.data === undefined) {
    return null;
  }

  return (
    <section className="mt-4 grid gap-2">
      <h2 className="font-medium">{t('detail.suppliers')}</h2>
      {links.data.length === 0 ? (
        <p className="text-ink-muted">{t('detail.noSuppliers')}</p>
      ) : (
        <DataGrid
          label={t('detail.suppliers')}
          columns={columns}
          source={{ kind: 'local', rows: links.data }}
          rowKey={(row) => row.supplier_id}
          exportName={exportName}
          onMessage={show}
        />
      )}
    </section>
  );
}
