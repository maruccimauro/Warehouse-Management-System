import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppProviders } from '@/app/AppProviders';
import { problemResponse, stubApi, testXsrfToken, userResponse } from '@/test/fakeApi';
import { LoginForm } from './LoginForm';

function renderForm() {
  render(
    <AppProviders>
      <LoginForm />
    </AppProviders>,
  );

  return {
    email: screen.getByLabelText('Correo electrónico'),
    password: screen.getByLabelText('Contraseña'),
    submit: screen.getByRole('button', { name: 'Ingresar' }),
  };
}

describe('formulario de login', () => {
  it('expone los campos con la semántica que necesitan el autocompletado y los gestores de contraseñas', () => {
    stubApi();
    const { email, password } = renderForm();

    expect(email).toHaveAttribute('type', 'email');
    expect(email).toHaveAttribute('autocomplete', 'username');
    expect(email).toBeRequired();
    expect(password).toHaveAttribute('type', 'password');
    expect(password).toHaveAttribute('autocomplete', 'current-password');
    expect(password).toBeRequired();
    // Sin errores al cargar: aria-invalid explícito en false.
    expect(email).toHaveAttribute('aria-invalid', 'false');
    expect(password).toHaveAttribute('aria-invalid', 'false');
  });

  it('valida en el cliente sin llamar a la API y enfoca el primer campo con error', async () => {
    const api = stubApi();
    const { email, password, submit } = renderForm();

    await userEvent.click(submit);

    expect(await screen.findByText('Ingrese el correo electrónico.')).toBeInTheDocument();
    expect(screen.getByText('Ingrese la contraseña.')).toBeInTheDocument();
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(email).toHaveAccessibleDescription('Ingrese el correo electrónico.');
    expect(password).toHaveAttribute('aria-invalid', 'true');
    expect(email).toHaveFocus();
    expect(api.requests).toHaveLength(0);
  });

  it('valida el formato del correo al salir del campo, no mientras se escribe', async () => {
    stubApi();
    const { email, password } = renderForm();

    await userEvent.type(email, 'operador');

    expect(screen.queryByText(/correo electrónico válido/)).not.toBeInTheDocument();

    await userEvent.click(password);

    expect(await screen.findByText('Ingrese un correo electrónico válido, por ejemplo nombre@empresa.com.')).toBeInTheDocument();

    await userEvent.type(email, '@wms.test');

    await waitFor(() => {
      expect(email).toHaveAttribute('aria-invalid', 'false');
    });
    expect(email).not.toHaveAccessibleDescription();
  });

  it('envía las credenciales con la cookie CSRF y el idioma de la interfaz', async () => {
    const api = stubApi({ 'POST /api/v1/auth/login': () => userResponse() });
    const { email, password, submit } = renderForm();

    await userEvent.type(email, '  operador@wms.test ');
    await userEvent.type(password, ' secreto ');
    await userEvent.click(submit);

    await waitFor(() => {
      expect(api.requestsTo('POST', '/api/v1/auth/login')).toHaveLength(1);
    });

    const [login] = api.requestsTo('POST', '/api/v1/auth/login');

    expect(api.requestsTo('GET', '/sanctum/csrf-cookie')).toHaveLength(1);
    // El correo se recorta; la contraseña viaja tal cual se escribió.
    expect(login?.body).toEqual({ email: 'operador@wms.test', password: ' secreto ' });
    expect(login?.headers.get('X-XSRF-TOKEN')).toBe(testXsrfToken);
    expect(login?.headers.get('Accept-Language')).toBe('es');
  });

  it('ante credenciales inválidas avisa sin decir qué dato falló, conserva el correo y vuelve a pedir la contraseña', async () => {
    stubApi({ 'POST /api/v1/auth/login': () => problemResponse(422, 'INVALID_CREDENTIALS') });
    const { email, password, submit } = renderForm();

    await userEvent.type(email, 'operador@wms.test');
    await userEvent.type(password, 'incorrecta');
    await userEvent.click(submit);

    expect(await screen.findByRole('alert')).toHaveTextContent('El correo electrónico o la contraseña no son correctos.');
    expect(email).toHaveValue('operador@wms.test');
    expect(password).toHaveValue('');
    expect(password).toHaveFocus();
  });

  it('muestra en cada campo los errores de validación del servidor', async () => {
    stubApi({
      'POST /api/v1/auth/login': () =>
        problemResponse(422, 'VALIDATION_FAILED', {
          errors: { email: ['El campo correo electrónico debe ser una dirección válida.'] },
        }),
    });
    const { email, password, submit } = renderForm();

    await userEvent.type(email, 'operador@wms.test');
    await userEvent.type(password, 'secreto');
    await userEvent.click(submit);

    await waitFor(() => {
      expect(email).toHaveAccessibleDescription('El campo correo electrónico debe ser una dirección válida.');
    });
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(email).toHaveFocus();
    expect(screen.getByRole('alert')).toBeEmptyDOMElement();
  });

  it('con demasiados intentos informa la espera del Retry-After y habilita el envío al terminar', async () => {
    stubApi({
      'POST /api/v1/auth/login': () => problemResponse(429, 'TOO_MANY_REQUESTS', {}, { 'Retry-After': '1' }),
    });
    const { email, password, submit } = renderForm();

    await userEvent.type(email, 'operador@wms.test');
    await userEvent.type(password, 'secreto');
    await userEvent.click(submit);

    expect(await screen.findByRole('alert')).toHaveTextContent('Podrá volver a intentar en 1 segundo.');
    expect(submit).toBeDisabled();
    expect(submit).toHaveTextContent('Disponible en 1 segundo');

    await waitFor(
      () => {
        expect(submit).toBeEnabled();
      },
      { timeout: 2500 },
    );
    expect(submit).toHaveTextContent('Ingresar');
    expect(screen.getByRole('alert')).toBeEmptyDOMElement();
  });

  it('informa la falta de conexión con el servidor', async () => {
    stubApi({
      'POST /api/v1/auth/login': () => Promise.reject(new TypeError('Failed to fetch')),
    });
    const { email, password, submit } = renderForm();

    await userEvent.type(email, 'operador@wms.test');
    await userEvent.type(password, 'secreto');
    await userEvent.click(submit);

    expect(await screen.findByRole('alert')).toHaveTextContent('No fue posible conectar con el servidor.');
    expect(submit).toBeEnabled();
  });

  it('permite ver la contraseña escrita', async () => {
    stubApi();
    const { password } = renderForm();
    const toggle = screen.getByRole('button', { name: 'Mostrar contraseña' });

    expect(toggle).toHaveAttribute('aria-pressed', 'false');

    await userEvent.click(toggle);

    expect(toggle).toHaveAttribute('aria-pressed', 'true');
    expect(password).toHaveAttribute('type', 'text');
  });
});
