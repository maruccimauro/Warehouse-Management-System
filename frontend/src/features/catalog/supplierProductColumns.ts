import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { GridColumn } from '@/shared/ui/grid/gridTypes';
import type { ProductSupplier } from './catalogApi';

// Columnas de los productos de un proveedor, compartidas con ZW06. La API ordena solo por
// producto y no filtra: la grilla no ofrece filtros ni otro orden.
export function useSupplierProductColumns(): GridColumn<ProductSupplier>[] {
  const { t } = useTranslation('suppliers');
  const { t: tProducts } = useTranslation('products');

  return useMemo(
    (): GridColumn<ProductSupplier>[] => [
      { id: 'product', header: t('fields.product'), value: (row) => row.product?.sku ?? '', filter: false },
      { id: 'productName', header: t('fields.productName'), value: (row) => row.product?.name ?? '', filter: false },
      {
        id: 'productStatus',
        header: t('fields.productStatus'),
        value: (row) => row.product?.status ?? '',
        format: (value) => (value === '' ? '' : tProducts(`statuses.${String(value) as 'active' | 'discontinued' | 'blocked'}`)),
        filter: false,
      },
      { id: 'supplierSku', header: t('fields.supplierSku'), value: (row) => row.supplier_sku, filter: false },
      { id: 'leadTimeDays', header: t('fields.leadTimeDays'), value: (row) => row.lead_time_days, type: 'number', filter: false },
    ],
    [t, tProducts],
  );
}
