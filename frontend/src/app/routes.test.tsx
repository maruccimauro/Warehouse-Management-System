import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderRoute } from '@/test/renderRoute';

describe('rutas', () => {
  it('muestra la página de inicio en español', () => {
    renderRoute('/');

    expect(screen.getByRole('heading', { level: 1, name: 'Sistema de gestión de depósitos' })).toBeInTheDocument();
    expect(document.documentElement.lang).toBe('es');
  });

  it('muestra la página no encontrada para una ruta inexistente y permite volver al inicio', async () => {
    renderRoute('/ruta-inexistente');

    expect(screen.getByRole('heading', { level: 1, name: 'Página no encontrada' })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('link', { name: 'Volver al inicio' }));

    expect(screen.getByRole('heading', { level: 1, name: 'Sistema de gestión de depósitos' })).toBeInTheDocument();
  });
});
