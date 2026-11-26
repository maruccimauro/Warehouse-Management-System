import { useEffect, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';
import { useTransactionActions } from '@/app/shell/shellActions';
import type { MaintenanceActivity } from '@/app/shell/useInitialScreenFlow';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { findProduct, normalizeSku, type Product } from './catalogApi';
import { ProductField } from './CatalogFields';
import { productLinkParams } from './productLinks';
import { InfoNotice } from '@/shared/ui/InfoNotice';

export type ProductActivity = MaintenanceActivity;

export interface ProductTarget {
  sku: string;
  // El producto leído; undefined al crear (todavía no existe).
  product: Product | undefined;
}

export interface ProductEntry {
  sku: string;
}

interface ProductInitialScreenProps {
  activity: ProductActivity;
  onContinue: (target: ProductTarget) => void;
  // Valores de la última entrada: F3 desde la ficha vuelve con lo ingresado.
  initial: ProductEntry | undefined;
}

// Pantalla inicial de crear, modificar y visualizar un artículo del catálogo (E2.13, D1): el SKU.
// F8 lee el artículo: crear exige que el SKU no exista y el resto, que exista.
export function ProductInitialScreen({ activity, onContinue, initial }: ProductInitialScreenProps) {
  const { t } = useTranslation('products');
  const { show } = useStatusBar();
  const [searchParams, setSearchParams] = useSearchParams();

  const fields = useMemo(
    (): SelectionField[] => [
      activity === 'create'
        ? { kind: 'parameter', id: 'sku', label: t('fields.sku'), normalize: normalizeSku, required: true }
        : { kind: 'parameter', id: 'sku', label: t('fields.sku'), as: ProductInput, required: true },
    ],
    [t, activity],
  );

  const linkedSku = searchParams.get(productLinkParams.sku);
  const selection = useSelectionScreen(fields, {
    sku: initial?.sku ?? linkedSku ?? '',
  });
  const running = useRef(false);

  const execute = async () => {
    const values = selection.validate(show);

    if (values === undefined || running.current) {
      return;
    }

    const sku = parameterValue(values, 'sku');
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

      onContinue({ sku, product });
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

  // Abierta desde un enlace con SKU (la lista del catálogo): lee el artículo sin esperar F8 y
  // quita el parámetro para que F3 vuelva a una pantalla inicial normal.
  useEffect(() => {
    if (linkedSku !== null) {
      setSearchParams({}, { replace: true });
      void execute();
    }
    // Solo al abrir desde el enlace.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [linkedSku !== null]);

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t(`initial.${activity}`)} onMessage={show} />
      <InfoNotice>{t('initial.hint')}</InfoNotice>
    </div>
  );
}

// Producto con F4 de servidor como entrada de la pantalla de selección: sin tabla de
// verificación, porque F8 lee el producto e informa si no existe.
function ProductInput(props: SelectionInputProps) {
  return <ProductField {...props} />;
}
