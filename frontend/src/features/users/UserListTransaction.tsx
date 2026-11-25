import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { useTransactionActions } from '@/app/shell/shellActions';
import { useStatusBar } from '@/app/shell/statusMessages';
import { VisibleWarehouseField } from '@/features/warehouses/WarehouseField';
import { useVisibleWarehouses } from '@/features/warehouses/warehouseQueries';
import { normalizeCode } from '@/features/warehouses/warehousesApi';
import type { ListFilterValue } from '@/shared/api/listing';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridSort } from '@/shared/ui/grid/gridTypes';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue, selectionFilters } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps, SelectionValues } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { RoleField, UserStatusField } from './UserFields';
import { useUserColumns } from './userColumns';
import { userLink } from './userLinks';
import { fetchUsers, normalizeUsername, usersQueryKey, type User } from './usersApi';

const exportName = 'usuarios';
const byUsername: GridSort = { columnId: 'username', desc: false };

// SUIM Lista de usuarios (E2.14): pantalla de selección con usuario y nombre (valores, rangos,
// patrón con * y exclusiones), rol, depósito asignado y estado; con F8, la grilla en modo
// servidor. Doble clic abre la visualización (ZW93).
export function UserListTransaction() {
  const { t } = useTranslation('users');
  const { show } = useStatusBar();
  const warehouses = useVisibleWarehouses();
  const [values, setValues] = useState<SelectionValues>();

  const fields = useMemo(
    (): SelectionField[] => [
      { kind: 'selectOptions', id: 'username', label: t('fields.username'), normalize: normalizeUsername },
      { kind: 'selectOptions', id: 'name', label: t('fields.name') },
      { kind: 'parameter', id: 'role', label: t('fields.role'), as: RoleInput },
      { kind: 'parameter', id: 'warehouse', label: t('fields.warehouse'), as: VisibleWarehouseField },
      { kind: 'parameter', id: 'status', label: t('fields.status'), as: StatusInput },
    ],
    [t],
  );
  const selection = useSelectionScreen(fields, {});

  const execute = () => {
    const validated = selection.validate(show);

    if (validated !== undefined) {
      setValues(validated);
    }
  };

  const filters = useMemo((): Record<string, ListFilterValue> | undefined => {
    if (values === undefined) {
      return undefined;
    }

    const role = parameterValue(values, 'role').trim();
    const warehouseCode = normalizeCode(parameterValue(values, 'warehouse'));
    const warehouseId = (warehouses.data ?? []).find((warehouse) => warehouse.code === warehouseCode)?.id;
    const status = parameterValue(values, 'status').trim();

    return {
      ...selectionFilters(fields, values),
      ...(role === '' ? {} : { role }),
      ...(warehouseId === undefined ? {} : { warehouse_id: String(warehouseId) }),
      ...(status === '' ? {} : { is_active: status === 'active' ? '1' : '0' }),
    };
  }, [values, fields, warehouses.data]);

  if (filters === undefined) {
    return <ListSelection selection={selection} onExecute={execute} />;
  }

  return (
    <UserResult
      filters={filters}
      onBack={() => {
        setValues(undefined);
      }}
    />
  );
}

function ListSelection({ selection, onExecute }: { selection: ReturnType<typeof useSelectionScreen>; onExecute: () => void }) {
  const { t } = useTranslation('users');
  const { show } = useStatusBar();

  useTransactionActions({ execute: onExecute });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t('list.selection')} onMessage={show} />
      <InfoNotice>{t('list.hint')}</InfoNotice>
    </div>
  );
}

// Resultado de la lista: también lo usa la pestaña Usuarios de un rol (PFCG).
export function UserResult({ filters, onBack, label }: { filters: Record<string, ListFilterValue>; onBack?: () => void; label?: string }) {
  const { t } = useTranslation('users');
  const { show } = useStatusBar();
  const navigate = useNavigate();
  const columns = useUserColumns();

  useTransactionActions({ back: onBack });

  const source = useMemo(
    () => ({
      kind: 'server' as const,
      queryKey: [...usersQueryKey, 'list', filters],
      fetchPage: (params: Parameters<typeof fetchUsers>[0], signal?: AbortSignal) => fetchUsers({ ...params, filters: { ...params.filters, ...filters } }, signal),
    }),
    [filters],
  );

  return (
    <DataGrid
      label={label ?? t('list.title')}
      columns={columns}
      source={source}
      rowKey={(row: User) => row.id}
      exportName={exportName}
      onMessage={show}
      onRowActivate={(row: User) => {
        void navigate(userLink('ZW93', row.username));
      }}
      initialSort={byUsername}
    />
  );
}

// Rol y estado con F4 local y tabla de verificación: un valor inexistente no sirve para acotar.
function RoleInput(props: SelectionInputProps) {
  return <RoleField {...props} />;
}

function StatusInput(props: SelectionInputProps) {
  return <UserStatusField {...props} />;
}
