import '@/i18n';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';
import { RouterProvider } from 'react-router/dom';
import { AppProviders } from '@/app/AppProviders';
import { routes } from '@/app/routes';

const container = document.getElementById('root');

if (container === null) {
  throw new Error('No existe el elemento #root en index.html.');
}

const router = createBrowserRouter(routes);

createRoot(container).render(
  <StrictMode>
    <AppProviders>
      <RouterProvider router={router} />
    </AppProviders>
  </StrictMode>,
);
