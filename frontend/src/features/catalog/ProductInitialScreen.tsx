import { useEffect, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import type { WarehouseSummary } from '@/features/auth/authApi';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { useDefaultWarehouse } from '@/features/warehouses/useDefaultWarehouse';
import { VisibleWarehouseField, WarehouseField } from '@/features/warehouses/WarehouseField';
import { useVisibleWarehouses } from '@/features/warehouses/warehouseQueries';
import { normalizeCode } from '@/features/warehouses/warehousesApi';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { findProduct, normalizeSku, type Product } from './catalogApi';
import { ProductField } from './CatalogFields';
import { productLinkParams } from './productLinks';

export type ProductActivity = 'create' | 'change' | 'display';

export interface ProductTarget {
  sku: string;
  // El producto leído; undefined al crear (todavía no existe).
  product: Product | undefined;
  // Depósito opcional de la pantalla inicial (D4): con él, la ficha suma el stock mínimo.
  warehouse: WarehouseSummary | undefined;
}

export interface ProductEntry {
  sku: string;
  warehouse: string;
}

interface ProductInitialScreenProps {
  activity: ProductActivity;
  onContinue: (target: ProductTarget) => void;
  // Valores de la última entrada: F3 desde la ficha vuelve con lo ingresado.
  initial: ProductEntry | undefined;
}

// Pantalla inicial de crear, modificar y visualizar un producto (E2.13, D1 y D4): SKU y, si se
// quiere ver o cargar el stock mínimo, un depósito. F8 lee el producto: crear exige que el SKU
// no exista y el resto, que exista.
export function ProductInitialScreen({ activity, onContinue, initial }: ProductInitialScreenProps) {
  const { t } = useTranslation('products');
  const { show } = useStatusBar();
  const user = useAuthenticatedUser();
  const visible = useVisibleWarehouses();
  const defaultWarehouse = useDefaultWarehouse();
  const [searchParams, setSearchParams] = useSearchParams();
  // Visualizar admite los depósitos que el usuario ve; crear y modificar el stock mínimo exige
  // operar en el depósito (REG-005).
  const consult = activity === 'display';
  const warehouses: readonly WarehouseSummary[] = consult ? (visible.data ?? []) : user.warehouses;

  const fields = useMemo(
    (): SelectionField[] => [
      activity === 'create'
        ? { kind: 'parameter', id: 'sku', label: t('fields.sku'), normalize: normalizeSku, required: true }
        : { kind: 'parameter', id: 'sku', label: t('fields.sku'), as: ProductInput, required: true },
      { kind: 'parameter', id: 'warehouse', label: t('fields.warehouse'), as: consult ? VisibleWarehouseField : WarehouseField },
    ],
    [t, consult, activity],
  );

  const linkedSku = searchParams.get(productLinkParams.sku);
  const selection = useSelectionScreen(fields, {
    sku: initial?.sku ?? linkedSku ?? '',
    warehouse: initial?.warehouse ?? defaultWarehouse ?? '',
  });
  const running = useRef(false);

  const execute = async () => {
    const values = selection.validate(show);

    if (values === undefined || running.current) {
      return;
    }

    const sku = parameterValue(values, 'sku');
    const warehouseCode = normalizeCode(parameterValue(values, 'warehouse'));
    const warehouse = warehouseCode === '' ? undefined : warehouses.find((candidate) => candidate.code === warehouseCode);

    // El campo ya informó el depósito inexistente: no se continúa con uno inválido.
    if (warehouseCode !== '' && warehouse === undefined) {
      return;
    }

    running.current = true;

    try {
      const product = await findProduct(sku);

      if (activity === 'create' && product !== undefined) {
        show({ type: 'E', text: t('messages.alreadyExists', { sku }), id: 'VALUE_ALREADY_EXISTS' });

        return;
      }

      if (activity !== 'create' && product === undefined) {
        show({ type: 'E', text: t('messages.notFound', { sku }), id: 'PRODUCT_NOT_FOUND' });

        return;
      }

      onContinue({ sku, product, warehouse });
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

  // Abierta desde un enlace con SKU (la lista de productos): lee el producto sin esperar F8 y
  // quita el parámetro para que F3 vuelva a una pantalla inicial normal. Con un depósito por
  // defecto en visualizar, espera a conocer los depósitos visibles.
  const ready = !consult || visible.data !== undefined;

  useEffect(() => {
    if (linkedSku !== null && ready) {
      setSearchParams({}, { replace: true });
      void execute();
    }
    // Solo al abrir desde el enlace.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [linkedSku !== null, ready]);

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t(`initial.${activity}`)} onMessage={show} />
      <p className="text-xs text-ink-muted">{t('initial.hint')}</p>
    </div>
  );
}

// Producto con F4 de servidor como entrada de la pantalla de selección: sin tabla de
// verificación, porque F8 lee el producto e informa si no existe.
function ProductInput(props: SelectionInputProps) {
  return <ProductField {...props} />;
}
