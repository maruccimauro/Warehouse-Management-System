import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query';
import { endSession, isPasswordChangeRequired, isUnauthenticated, requirePasswordChange } from '@/features/auth/session';
import { ApiError } from '@/shared/api/ApiError';

const maxQueryRetries = 2;

// Las consultas se reintentan solo ante fallas transitorias (red, 502, 503, 504): un 4xx
// no cambia al repetirse y un error de programación no es un ApiError. Las mutaciones no se
// reintentan solas; las que modifican stock reenvían con la misma Idempotency-Key (ADR-007).
export function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  return error instanceof ApiError && error.isRetryable && failureCount < maxQueryRetries;
}

export function createQueryClient(): QueryClient {
  // Un 401 en cualquier consulta o mutación significa que la sesión expiró: se descarta la
  // caché y la sesión pasa a null, con lo que RequireAuth lleva al login desde cualquier
  // pantalla. La consulta de sesión no entra acá porque convierte el 401 en null.
  // Un 403 PASSWORD_CHANGE_REQUIRED (REG-021) vuelve al cambio de contraseña obligatorio.
  const onError = (error: unknown) => {
    if (isUnauthenticated(error)) {
      endSession(queryClient);
    } else if (isPasswordChangeRequired(error)) {
      requirePasswordChange(queryClient);
    }
  };

  const queryClient: QueryClient = new QueryClient({
    queryCache: new QueryCache({ onError }),
    mutationCache: new MutationCache({ onError }),
    defaultOptions: {
      queries: {
        // Evita repetir peticiones al montar pantallas consecutivas que piden lo mismo.
        staleTime: 30_000,
        retry: shouldRetryQuery,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return queryClient;
}
