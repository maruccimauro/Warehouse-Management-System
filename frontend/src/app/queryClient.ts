import { QueryClient } from '@tanstack/react-query';

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        // Evita repetir peticiones al montar pantallas consecutivas que piden lo mismo.
        staleTime: 30_000,
      },
    },
  });
}
