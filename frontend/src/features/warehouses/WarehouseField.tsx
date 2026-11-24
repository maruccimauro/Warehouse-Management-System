import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { WarehouseSummary } from '@/features/auth/authApi';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { SearchHelpField, type SearchHelpFieldProps } from '@/shared/ui/searchHelp/SearchHelpField';
import type { SearchHelp } from '@/shared/ui/searchHelp/searchHelpTypes';

type WarehouseFieldProps = Omit<SearchHelpFieldProps<WarehouseSummary>, 'searchHelp'>;

// Campo Depósito con F4 (E2.17, ADR-016). Ofrece y acepta solo los depósitos donde el usuario
// opera (me.warehouses: los asignados, o todos con warehouses.operate_all; REG-005). Las
// pantallas operativas lo completan con useDefaultWarehouse (parámetro LGN).
export function WarehouseField(props: WarehouseFieldProps) {
  const { t } = useTranslation('warehouses');
  const user = useAuthenticatedUser();

  const searchHelp = useMemo(
    (): SearchHelp<WarehouseSummary> => ({
      title: t('searchHelp.title'),
      columns: [
        { id: 'code', header: t('searchHelp.code'), value: (row) => row.code },
        { id: 'name', header: t('searchHelp.name'), value: (row) => row.name },
      ],
      source: { kind: 'local', rows: user.warehouses },
      criteria: ['code', 'name'],
      valueOf: (row) => row.code,
      descriptionOf: (row) => row.name,
      // Los códigos se guardan en mayúsculas (normalizeCodes del backend).
      normalize: (text) => text.trim().toUpperCase(),
      invalidMessage: (code) => t('searchHelp.invalid', { code }),
      exportName: 'depositos',
    }),
    [t, user.warehouses],
  );

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}
