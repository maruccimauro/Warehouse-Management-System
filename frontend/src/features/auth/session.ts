import { queryOptions, useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/shared/api/ApiError';
import { fetchCurrentUser, login, logout, type AuthenticatedUser } from './authApi';

export const sessionQueryKey = ['auth', 'session'] as const;

export function isUnauthenticated(error: unknown): boolean {
  return error instanceof ApiError && error.code === 'UNAUTHENTICATED';
}

// La sesión es el resultado de GET /auth/me: un 401 UNAUTHENTICATED no es un error sino
// "no hay sesión" (null). Cualquier otra falla (red, 500) queda como error de la consulta.
export const sessionQueryOptions = queryOptions({
  queryKey: sessionQueryKey,
  queryFn: async ({ signal }): Promise<AuthenticatedUser | null> => {
    try {
      return await fetchCurrentUser(signal);
    } catch (error) {
      if (isUnauthenticated(error)) {
        return null;
      }

      throw error;
    }
  },
});

// Sin sesión se descarta todo lo cacheado: los datos de un usuario no quedan visibles
// para el siguiente que ingrese en el mismo navegador.
export function endSession(queryClient: QueryClient): void {
  queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== sessionQueryKey[0] });
  queryClient.setQueryData(sessionQueryKey, null);
}

export function useSession() {
  return useQuery(sessionQueryOptions);
}

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: login,
    // La respuesta del login ya es el usuario autenticado: se escribe en la caché en lugar
    // de invalidar, lo que evita un GET /auth/me extra. Las guardas de rutas reaccionan solas.
    onSuccess: (user) => {
      queryClient.setQueryData(sessionQueryKey, user);
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();

  // Si la sesión ya había expirado, el 401 lo resuelve el manejador global del QueryClient
  // con el mismo resultado para el usuario.
  return useMutation({
    mutationFn: logout,
    onSuccess: () => {
      endSession(queryClient);
    },
  });
}
