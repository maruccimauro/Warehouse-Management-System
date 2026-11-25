import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useInitialScreenFlow, type MaintenanceActivity } from '@/app/shell/useInitialScreenFlow';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { HelpLink } from '@/shared/help/HelpLink';
import { normalizeTaxId, optionalEmail, requiredText, taxIdRule, textRule } from '@/shared/ui/form/fieldRules';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridSource } from '@/shared/ui/grid/gridTypes';
import {
  createSupplier,
  emailMaxLength,
  fetchSupplierProducts,
  phoneMaxLength,
  supplierNameMaxLength,
  supplierProductsQueryKey,
  suppliersQueryKey,
  updateSupplier,
  type ProductSupplier,
  type Supplier,
  type SupplierInput,
} from './catalogApi';
import { SupplierInitialScreen } from './SupplierInitialScreen';
import { useSupplierProductColumns } from './supplierProductColumns';
import { InfoNotice } from '@/shared/ui/InfoNotice';

type SupplierActivity = MaintenanceActivity;

// XK01, XK02 y XK03: crear, modificar y visualizar un proveedor (E2.13, D1). Modificar y
// visualizar parten de la pantalla inicial con el identificador fiscal; crear abre la ficha
// directamente, porque el proveedor no tiene un código propio que verificar antes: el
// identificador fiscal repetido se informa al grabar (VALUE_ALREADY_EXISTS).
export function SupplierTransaction({ activity }: { activity: SupplierActivity }) {
  const flow = useInitialScreenFlow((next: Supplier) => next.tax_id);
  // Crear: cada grabación vuelve a una ficha vacía para el alta siguiente.
  const [round, setRound] = useState(0);

  if (activity === 'create') {
    return (
      <SupplierDetail
        key={round}
        activity="create"
        supplier={undefined}
        onDone={() => {
          setRound((current) => current + 1);
        }}
      />
    );
  }

  if (flow.target === undefined) {
    return <SupplierInitialScreen title={activity} initial={flow.last} onContinue={flow.open} />;
  }

  return <SupplierDetail activity={activity} supplier={flow.target} onDone={flow.close} />;
}

export function CreateSupplierTransaction() {
  return <SupplierTransaction activity="create" />;
}

export function ChangeSupplierTransaction() {
  return <SupplierTransaction activity="change" />;
}

export function DisplaySupplierTransaction() {
  return <SupplierTransaction activity="display" />;
}

const supplierFormSchema = z.object({
  taxId: taxIdRule(),
  name: requiredText(supplierNameMaxLength),
  email: optionalEmail(emailMaxLength),
  phone: textRule(phoneMaxLength),
  status: z.enum(['active', 'inactive']),
});

const nullable = (text: string) => (text.trim() === '' ? null : text.trim());

interface SupplierDetailProps {
  activity: SupplierActivity;
  supplier: Supplier | undefined;
  onDone: () => void;
}

function SupplierDetail({ activity, supplier, onDone }: SupplierDetailProps) {
  const { t } = useTranslation('suppliers');
  const queryClient = useQueryClient();

  const defaultValues = useMemo(
    () => ({
      taxId: supplier?.tax_id ?? '',
      name: supplier?.name ?? '',
      email: supplier?.email ?? '',
      phone: supplier?.phone ?? '',
      status: supplier?.is_active === false ? ('inactive' as const) : ('active' as const),
    }),
    [supplier],
  );

  const { Form, Section, Field, values } = useTransactionForm({
    schema: supplierFormSchema,
    defaultValues,
    namespace: 'suppliers',
    readOnly: activity === 'display',
    savedMessage: t(activity === 'create' ? 'messages.created' : 'messages.saved', { name: supplier?.name ?? '' }),
    saveFailedMessage: t('messages.saveFailed'),
    serverFields: { tax_id: 'taxId', is_active: 'status' },
    // Crear no tiene pantalla inicial: F3 sale de la transacción, como en el resto.
    onBack: activity === 'create' ? undefined : onDone,
    onSave: async (form) => {
      const input: SupplierInput = {
        tax_id: normalizeTaxId(form.taxId),
        name: form.name,
        email: nullable(form.email),
        phone: nullable(form.phone),
      };
      const saved =
        activity === 'create'
          ? await createSupplier({ ...input, tax_id: normalizeTaxId(form.taxId), name: form.name })
          : await updateSupplier(supplier?.id ?? 0, { ...input, is_active: form.status === 'active' });

      void queryClient.invalidateQueries({ queryKey: suppliersQueryKey });
      onDone();

      return t(activity === 'create' ? 'messages.created' : 'messages.saved', { name: saved.name });
    },
  });

  const statusOptions = [
    { value: 'active', label: t('statuses.active') },
    { value: 'inactive', label: t('statuses.inactive') },
  ];

  return (
    <div className="grid gap-3">
      {activity === 'create' && <h2 className="font-medium">{t('detail.createTitle')}</h2>}
      <Form>
        <Section name="general">
          <Field name="taxId" required className="w-64" description={activity === 'display' ? undefined : t('detail.taxIdHint')} />
          <Field name="name" required />
          {activity !== 'create' && <Field name="status" options={statusOptions} />}
        </Section>
        <Section name="contact">
          <Field name="email" type="email" />
          <Field name="phone" className="w-64" />
        </Section>
      </Form>
      {supplier !== undefined && (
        <p className="text-ink-muted">
          {t('fields.status')}: <HelpLink helpKey={`state.supplier.${values.status}`}>{t(`statuses.${values.status}`)}</HelpLink>
        </p>
      )}
      {activity !== 'display' && <InfoNotice>{t('detail.saveHint')}</InfoNotice>}
      {activity === 'display' && supplier !== undefined && <SupplierProductsReadOnly supplier={supplier} />}
    </div>
  );
}

// Productos vinculados, de solo lectura en la visualización (D5): se mantienen en ZW06.
function SupplierProductsReadOnly({ supplier }: { supplier: Supplier }) {
  const { t } = useTranslation('suppliers');
  const { show } = useStatusBar();
  const columns = useSupplierProductColumns();

  const source = useMemo(
    (): GridSource<ProductSupplier> => ({
      kind: 'server',
      queryKey: [...supplierProductsQueryKey(supplier.id), 'grid'],
      fetchPage: (params, signal) => fetchSupplierProducts(supplier.id, params, signal),
    }),
    [supplier.id],
  );

  return (
    <section className="mt-4 grid gap-2">
      <h2 className="font-medium">{t('detail.products')}</h2>
      <DataGrid
        label={t('detail.products')}
        columns={columns}
        source={source}
        rowKey={(row) => row.product_id}
        exportName={`productos-${supplier.tax_id.toLowerCase()}`}
        onMessage={show}
      />
    </section>
  );
}
