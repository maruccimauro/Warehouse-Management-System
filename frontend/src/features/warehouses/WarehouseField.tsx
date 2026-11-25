import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { WarehouseSummary } from '@/features/auth/authApi';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { SearchHelpField, type SearchHelpFieldProps } from '@/shared/ui/searchHelp/SearchHelpField';
import type { SearchHelp } from '@/shared/ui/searchHelp/searchHelpTypes';
import { useVisibleWarehouses } from './warehouseQueries';
import { normalizeCode } from './warehousesApi';

type WarehouseFieldProps = Omit<SearchHelpFieldProps<WarehouseSummary>, 'searchHelp'>;

function useWarehouseSearchHelp(rows: readonly WarehouseSummary[], invalidKey: 'invalid' | 'invalidVisible', loading = false) {
  const { t } = useTranslation('warehouses');

  return useMemo(
    (): SearchHelp<WarehouseSummary> => ({
      title: t('searchHelp.title'),
      columns: [
        { id: 'code', header: t('searchHelp.code'), value: (row) => row.code },
        { id: 'name', header: t('searchHelp.name'), value: (row) => row.name },
      ],
      source: { kind: 'local', rows, loading },
      criteria: ['code', 'name'],
      valueOf: (row) => row.code,
      descriptionOf: (row) => row.name,
      normalize: normalizeCode,
      invalidMessage: (code) => t(`searchHelp.${invalidKey}`, { code }),
      exportName: 'depositos',
    }),
    [t, rows, invalidKey, loading],
  );
}

// Campo Depósito con F4 (E2.17, ADR-016) para operar. Ofrece y acepta solo los depósitos donde
// el usuario opera (me.warehouses: los asignados, o todos con warehouses.operate_all; REG-005).
// Las pantallas operativas lo completan con useDefaultWarehouse (parámetro LGN).
export function WarehouseField(props: WarehouseFieldProps) {
  const user = useAuthenticatedUser();
  const searchHelp = useWarehouseSearchHelp(user.warehouses, 'invalid');

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}

// Campo Depósito para consultar (E2.12): visualizar y listar ubicaciones también en los
// depósitos que el usuario no opera.
export function VisibleWarehouseField(props: WarehouseFieldProps) {
  const { data, isPending } = useVisibleWarehouses();
  const searchHelp = useWarehouseSearchHelp(data ?? noWarehouses, 'invalidVisible', isPending);

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}

const noWarehouses: readonly WarehouseSummary[] = [];
