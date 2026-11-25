import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, StatusMessageError, useStatusBar } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog';
import { nonNegativeInteger, required, textRule } from '@/shared/ui/form/fieldRules';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridSource } from '@/shared/ui/grid/gridTypes';
import {
  fetchProductSuppliers,
  fetchSupplierProducts,
  findProduct,
  normalizeSku,
  leadTimeMaxDays,
  productDetailQueryKey,
  removeProductSupplier,
  setProductSupplier,
  supplierProductsQueryKey,
  supplierSkuMaxLength,
  type ProductSupplier,
  type Supplier,
} from './catalogApi';
import { ProductField } from './CatalogFields';
import { SupplierInitialScreen } from './SupplierInitialScreen';
import { useSupplierProductColumns } from './supplierProductColumns';
import { InfoNotice } from '@/shared/ui/InfoNotice';

type Screen =
  | { kind: 'selection' }
  | { kind: 'list'; supplier: Supplier }
  | { kind: 'detail'; supplier: Supplier; mode: 'create' | 'display' | 'change'; link?: ProductSupplier };

// ZW06: productos del proveedor (E2.13, D5). Pantalla inicial con el proveedor (F8), la lista de
// productos vinculados y la ficha del vínculo, con el código que usa el proveedor y el plazo de
// entrega. Cada acción se graba en el momento, como en ZW02; el vínculo habilita el producto en
// las órdenes de compra a ese proveedor.
export function SupplierProductsTransaction() {
  const [screen, setScreen] = useState<Screen>({ kind: 'selection' });
  const [last, setLast] = useState<string>();

  if (screen.kind === 'selection') {
    return (
      <SupplierInitialScreen
        title="products"
        initial={last}
        onContinue={(supplier) => {
          setLast(supplier.tax_id);
          setScreen({ kind: 'list', supplier });
        }}
      />
    );
  }

  if (screen.kind === 'list') {
    return (
      <LinkList
        supplier={screen.supplier}
        onBack={() => {
          setScreen({ kind: 'selection' });
        }}
        onOpen={(link) => {
          setScreen({ kind: 'detail', supplier: screen.supplier, mode: 'display', link });
        }}
        onCreate={() => {
          setScreen({ kind: 'detail', supplier: screen.supplier, mode: 'create' });
        }}
      />
    );
  }

  return (
    <LinkDetail
      key={`${screen.mode}-${String(screen.link?.product_id ?? 'new')}`}
      supplier={screen.supplier}
      mode={screen.mode}
      link={screen.link}
      onModeChange={(mode, link) => {
        setScreen({ kind: 'detail', supplier: screen.supplier, mode, link });
      }}
      onBack={() => {
        setScreen({ kind: 'list', supplier: screen.supplier });
      }}
    />
  );
}

interface LinkListProps {
  supplier: Supplier;
  onBack: () => void;
  onOpen: (link: ProductSupplier) => void;
  onCreate: () => void;
}

function LinkList({ supplier, onBack, onOpen, onCreate }: LinkListProps) {
  const { t } = useTranslation('suppliers');
  const { show } = useStatusBar();
  const columns = useSupplierProductColumns();

  useTransactionActions({ back: onBack });

  const source = useMemo(
    (): GridSource<ProductSupplier> => ({
      kind: 'server',
      queryKey: [...supplierProductsQueryKey(supplier.id), 'grid'],
      fetchPage: (params, signal) => fetchSupplierProducts(supplier.id, params, signal),
    }),
    [supplier.id],
  );

  const actions = [{ id: 'create', label: t('links.create'), onClick: onCreate }];

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('links.supplier', { taxId: supplier.tax_id, name: supplier.name })}</p>
      <ApplicationToolbar label={t('links.actions')} actions={actions} />
      <DataGrid
        label={t('links.title', { name: supplier.name })}
        columns={columns}
        source={source}
        rowKey={(row) => row.product_id}
        exportName={`productos-${supplier.tax_id.toLowerCase()}`}
        onMessage={show}
        onRowActivate={onOpen}
      />
      <InfoNotice>{t('links.hint')}</InfoNotice>
    </div>
  );
}

const linkSchema = z.object({
  product: required(),
  supplierSku: textRule(supplierSkuMaxLength),
  leadTimeDays: nonNegativeInteger(leadTimeMaxDays, { optional: true }),
});

interface LinkDetailProps {
  supplier: Supplier;
  mode: 'create' | 'display' | 'change';
  link: ProductSupplier | undefined;
  onModeChange: (mode: 'display' | 'change', link: ProductSupplier) => void;
  onBack: () => void;
}

function LinkDetail({ supplier, mode, link, onModeChange, onBack }: LinkDetailProps) {
  const { t } = useTranslation('suppliers');
  const { show } = useStatusBar();
  const queryClient = useQueryClient();
  const [confirmingRemoval, setConfirmingRemoval] = useState(false);

  const defaultValues = useMemo(
    () => ({
      product: link?.product?.sku ?? '',
      supplierSku: link?.supplier_sku ?? '',
      leadTimeDays: link?.lead_time_days == null ? '' : String(link.lead_time_days),
    }),
    [link],
  );

  const invalidate = (productId: number) => {
    // En segundo plano: el mensaje y el cambio de pantalla no esperan a la lista.
    void queryClient.invalidateQueries({ queryKey: supplierProductsQueryKey(supplier.id) });
    void queryClient.invalidateQueries({ queryKey: productDetailQueryKey(productId) });
  };

  const { Form, Section, Field } = useTransactionForm({
    schema: linkSchema,
    defaultValues,
    namespace: 'suppliers',
    readOnly: mode === 'display',
    savedMessage: t('links.saved', { sku: link?.product?.sku ?? '' }),
    saveFailedMessage: t('links.saveFailed'),
    serverFields: { supplier_sku: 'supplierSku', lead_time_days: 'leadTimeDays' },
    onBack,
    onSave: async (form) => {
      const input = {
        supplier_sku: form.supplierSku.trim() === '' ? null : form.supplierSku.trim(),
        lead_time_days: form.leadTimeDays.trim() === '' ? null : Number(form.leadTimeDays),
      };

      if (mode !== 'create' && link !== undefined) {
        const saved = await setProductSupplier(supplier.id, link.product_id, input);

        invalidate(link.product_id);
        onModeChange('display', { ...saved, product: link.product });

        return undefined;
      }

      // Alta: el SKU tiene que existir y no estar vinculado todavía, porque el PUT reemplaza el
      // vínculo completo y pisaría los datos del existente sin avisar.
      const sku = normalizeSku(form.product);
      const product = await findProduct(sku);

      if (product === undefined) {
        throw new StatusMessageError({ type: 'E', text: t('links.productNotFound', { sku }), id: 'PRODUCT_NOT_FOUND' });
      }

      const existing = await fetchProductSuppliers(product.id);

      if (existing.some((candidate) => candidate.supplier_id === supplier.id)) {
        throw new StatusMessageError({ type: 'E', text: t('links.alreadyLinked', { sku }), id: 'VALUE_ALREADY_EXISTS' });
      }

      const saved = await setProductSupplier(supplier.id, product.id, input);

      invalidate(product.id);
      onModeChange('display', { ...saved, product: { sku: product.sku, name: product.name, status: product.status } });

      return t('links.created', { sku });
    },
  });

  const remove = async () => {
    setConfirmingRemoval(false);

    if (link === undefined) {
      return;
    }

    try {
      await removeProductSupplier(supplier.id, link.product_id);
      invalidate(link.product_id);
      show({ type: 'S', text: t('links.removed', { sku: link.product?.sku ?? '' }) });
      onBack();
    } catch (error) {
      show(errorMessage(error, t('links.removeFailed')));
    }
  };

  const actions: ApplicationAction[] =
    mode === 'display' && link !== undefined
      ? [
          {
            id: 'change',
            label: t('links.change'),
            onClick: () => {
              onModeChange('change', link);
            },
          },
          {
            id: 'remove',
            label: t('links.remove'),
            onClick: () => {
              setConfirmingRemoval(true);
            },
          },
        ]
      : [];

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('links.supplier', { taxId: supplier.tax_id, name: supplier.name })}</p>
      <ApplicationToolbar label={t('links.detailActions')} actions={actions} />
      <Form>
        <Section name="link">
          {mode === 'create' ? (
            <Field name="product" as={ProductField} required className="w-64" />
          ) : (
            <Field name="product" readOnly className="w-64" description={link?.product?.name} />
          )}
          <Field name="supplierSku" className="w-64" description={mode === 'display' ? undefined : t('links.supplierSkuHint')} />
          <Field
            name="leadTimeDays"
            inputMode="numeric"
            className="w-24"
            description={mode === 'display' ? undefined : t('links.leadTimeHint', { max: leadTimeMaxDays })}
          />
        </Section>
      </Form>
      {link !== undefined && (
        <ConfirmDialog
          open={confirmingRemoval}
          title={t('links.removeTitle')}
          message={t('links.removeConfirm', { sku: link.product?.sku ?? '', name: supplier.name })}
          confirmLabel={t('links.remove')}
          cancelLabel={t('links.cancel')}
          onConfirm={() => {
            void remove();
          }}
          onCancel={() => {
            setConfirmingRemoval(false);
          }}
        />
      )}
    </div>
  );
}
