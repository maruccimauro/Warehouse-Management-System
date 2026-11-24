import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { useUserPreferences } from '@/features/preferences/userPreferences';

// Depósito por defecto de las pantallas operativas: el parámetro LGN de SU3, si el depósito
// sigue entre los que el usuario opera (ADR-016, REG-005). Uno que dejó de estar asignado no
// completa el campo, como un valor de parámetro que ya no pasa la tabla de verificación.
export function useDefaultWarehouse(): string | undefined {
  const user = useAuthenticatedUser();
  const { preferences } = useUserPreferences();
  const lgn = preferences.parameters.LGN;

  return lgn === null ? undefined : user.warehouses.find((warehouse) => warehouse.code === lgn)?.code;
}
