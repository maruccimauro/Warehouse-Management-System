import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchRoles, isRoleName, rolesQueryKey } from './usersApi';

// Roles con sus permisos (GET /roles): cinco filas fijas que define el backend.
export function useRoles() {
  return useQuery({ queryKey: rolesQueryKey, queryFn: ({ signal }) => fetchRoles(signal), staleTime: Number.POSITIVE_INFINITY });
}

// Nombre visible de un rol: lo traduce la interfaz (los roles son datos del sistema). Un rol
// que la interfaz todavía no conoce se muestra con su identificador.
export function useRoleLabel() {
  const { t } = useTranslation('roles');

  return (name: string) => (isRoleName(name) ? t(`names.${name}`) : name);
}

export const userStatuses = ['active', 'inactive'] as const;

// Valor de una lista de casillas en el formulario: los elegidos separados por coma, porque los
// bloques de transacción trabajan con texto (E2.22).
export function checkListValue(values: readonly string[]): string {
  return [...values].sort().join(',');
}

export function checkListValues(value: string): string[] {
  return value === '' ? [] : value.split(',');
}
