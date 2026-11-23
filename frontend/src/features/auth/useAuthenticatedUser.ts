import { use } from 'react';
import type { AuthenticatedUser } from './authApi';
import { AuthenticatedUserContext } from './authenticatedUserContext';

// Solo para componentes bajo RequireAuth.
export function useAuthenticatedUser(): AuthenticatedUser {
  const user = use(AuthenticatedUserContext);

  if (user === undefined) {
    throw new Error('useAuthenticatedUser se usó fuera de una ruta protegida.');
  }

  return user;
}
