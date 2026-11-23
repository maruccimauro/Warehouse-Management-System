import { QueryClientProvider } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';
import { createQueryClient } from './queryClient';

export function AppProviders({ children }: { children: ReactNode }) {
  // Un QueryClient por árbol: las pruebas no comparten caché entre sí.
  const [queryClient] = useState(createQueryClient);

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
