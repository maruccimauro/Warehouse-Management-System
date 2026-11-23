import type { RouteObject } from 'react-router';
import { AppShell } from '@/app/shell/AppShell';
import { TransactionScreen } from '@/app/shell/TransactionScreen';
import { GuestOnly, RequireAuth } from '@/features/auth/RouteGuards';
import { HomePage } from '@/pages/HomePage';
import { LoginPage } from '@/pages/LoginPage';
import { NotFoundPage } from '@/pages/NotFoundPage';

// Rutas de nivel superior: el login solo sin sesión; el resto, con sesión y dentro del
// armazón. Cada transacción es /t/CÓDIGO (ADR-016). La página no encontrada no requiere sesión.
export const routes: RouteObject[] = [
  {
    element: <GuestOnly />,
    children: [{ path: '/login', element: <LoginPage /> }],
  },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppShell />,
        children: [
          { index: true, element: <HomePage /> },
          { path: 't/:code', element: <TransactionScreen /> },
        ],
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
];
