import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/shared/api/ApiError';

const maxQueryRetries = 2;

// Las consultas se reintentan solo ante fallas transitorias (red, 502, 503, 504): un 4xx
// no cambia al repetirse y un error de programación no es un ApiError. Las mutaciones no se
// reintentan solas; las que modifican stock reenvían con la misma Idempotency-Key (ADR-007).
export function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  return error instanceof ApiError && error.isRetryable && failureCount < maxQueryRetries;
}

export function createQueryClient(): QueryClient {
  return new QueryClient({
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
}
