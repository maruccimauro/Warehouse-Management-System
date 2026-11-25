import { useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useInitialScreenFlow, type MaintenanceActivity } from '@/app/shell/useInitialScreenFlow';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import {
  fetchProductSuppliers,
  findProduct,
  findSupplier,
  normalizeSku,
  normalizeSupplierNumber,
  type ProductSupplier,
  type Supplier,
} from './catalogApi';
import { ProductField, SupplierField } from './CatalogFields';
import { LinkDetail } from './SupplierProductsTransaction';

// ZW61, ZW62 y ZW63: crear, modificar y visualizar el vínculo de un artículo con un proveedor,
// cada acción con su transacción (ADR-016), además de la gestión con lista y ficha de ZW06.
// Usan la misma ficha que ZW06. Crear pide solo el proveedor: la ficha pide el artículo y, al
// grabar, verifica que exista y que no esté vinculado, como en ZW06.

interface LinkTarget {
  supplier: Supplier;
  // undefined al crear: el vínculo todavía no existe.
  link: ProductSupplier | undefined;
}

interface LinkEntry {
  supplier: string;
  product: string;
}

export function CreateSupplierProductTransaction() {
  return <LinkActivity activity="create" />;
}

export function ChangeSupplierProductTransaction() {
  return <LinkActivity activity="change" />;
}

export function DisplaySupplierProductTransaction() {
  return <LinkActivity activity="display" />;
}

function LinkActivity({ activity }: { activity: MaintenanceActivity }) {
  const flow = useInitialScreenFlow(
    (next: LinkTarget): LinkEntry => ({ supplier: next.supplier.number, product: next.link?.product?.sku ?? '' }),
  );

  if (flow.target === undefined) {
    return <LinkInitialScreen activity={activity} initial={flow.last} onContinue={flow.open} />;
  }

  return (
    <LinkDetail
      supplier={flow.target.supplier}
      mode={activity}
      link={flow.target.link}
      standalone
      // Grabar o quitar el vínculo vuelve a la pantalla inicial, lista para el siguiente.
      onModeChange={flow.close}
      onBack={flow.close}
    />
  );
}

interface LinkInitialScreenProps {
  activity: MaintenanceActivity;
  initial: LinkEntry | undefined;
  onContinue: (target: LinkTarget) => void;
}

function LinkInitialScreen({ activity, initial, onContinue }: LinkInitialScreenProps) {
  const { t } = useTranslation('suppliers');
  const { show } = useStatusBar();
  const running = useRef(false);

  const fields = useMemo((): SelectionField[] => {
    const supplierField: SelectionField = { kind: 'parameter', id: 'supplier', label: t('fields.supplier'), as: SupplierInput, required: true };

    return activity === 'create'
      ? [supplierField]
      : [supplierField, { kind: 'parameter', id: 'product', label: t('fields.product'), as: ProductInput, required: true }];
  }, [t, activity]);

  const selection = useSelectionScreen(fields, { supplier: initial?.supplier ?? '', product: initial?.product ?? '' });

  const execute = async () => {
    const values = selection.validate(show);

    if (values === undefined || running.current) {
      return;
    }

    const number = normalizeSupplierNumber(parameterValue(values, 'supplier'));
    running.current = true;

    try {
      const supplier = /^\d+$/.test(number) ? await findSupplier(number) : undefined;

      if (supplier === undefined) {
        show({ type: 'E', text: t('messages.notFound', { number }), id: 'SUPPLIER_NOT_FOUND' });

        return;
      }

      if (activity === 'create') {
        onContinue({ supplier, link: undefined });

        return;
      }

      const sku = normalizeSku(parameterValue(values, 'product'));
      const product = await findProduct(sku);

      if (product === undefined) {
        show({ type: 'E', text: t('links.productNotFound', { sku }), id: 'PRODUCT_NOT_FOUND' });

        return;
      }

      const link = (await fetchProductSuppliers(product.id)).find((candidate) => candidate.supplier_id === supplier.id);

      if (link === undefined) {
        show({ type: 'E', text: t('links.notLinked', { sku, number: supplier.number }), id: 'SUPPLIER_PRODUCT_NOT_FOUND' });

        return;
      }

      onContinue({ supplier, link: { ...link, product: { sku: product.sku, name: product.name, status: product.status } } });
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
      <SelectionScreen selection={selection} title={t(`linkInitial.${activity}`)} onMessage={show} />
      <InfoNotice>{t(activity === 'create' ? 'linkInitial.createHint' : 'linkInitial.hint')}</InfoNotice>
    </div>
  );
}

// Proveedor y artículo con F4 de servidor, sin tabla de verificación: F8 los lee e informa si
// no existen.
function SupplierInput(props: SelectionInputProps) {
  return <SupplierField {...props} />;
}

function ProductInput(props: SelectionInputProps) {
  return <ProductField {...props} />;
}
