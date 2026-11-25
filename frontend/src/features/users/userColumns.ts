import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { GridColumn } from '@/shared/ui/grid/gridTypes';
import { useRoleLabel } from './userValues';
import type { User } from './usersApi';

// Columnas de la lista de usuarios (SUIM) y de la pestaña Usuarios de un rol (PFCG). El listado
// ordena por código, nombre y email; los criterios se acotan en la pantalla de selección.
export function useUserColumns(): GridColumn<User>[] {
  const { t } = useTranslation('users');
  const roleLabel = useRoleLabel();

  return useMemo(
    (): GridColumn<User>[] => [
      { id: 'username', header: t('fields.username'), value: (row) => row.username, sortable: true, filter: false },
      { id: 'name', header: t('fields.name'), value: (row) => row.name, sortable: true, filter: false },
      { id: 'email', header: t('fields.email'), value: (row) => row.email, sortable: true, filter: false },
      { id: 'roles', header: t('fields.roles'), value: (row) => row.roles.map(roleLabel).join(', '), filter: false },
      { id: 'warehouses', header: t('fields.warehouses'), value: (row) => row.warehouses.map((warehouse) => warehouse.code).join(', '), filter: false },
      {
        id: 'is_active',
        header: t('fields.status'),
        value: (row) => (row.is_active ? 'active' : 'inactive'),
        format: (value) => t(`statuses.${String(value) as 'active' | 'inactive'}`),
        filter: false,
      },
      {
        id: 'password_change_required',
        header: t('list.passwordChangeRequired'),
        value: (row) => (row.password_change_required ? 'yes' : 'no'),
        format: (value) => t(value === 'yes' ? 'yes' : 'no'),
        filter: false,
      },
    ],
    // roleLabel depende solo de la traducción de roles.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [t],
  );
}
