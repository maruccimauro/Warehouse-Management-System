import { z } from 'zod';
import { problemMessage } from '@/shared/errors/problemMessage';
import { ApiError } from './ApiError';
import { createApiClient } from './apiClient';

interface Call {
  url: string;
  init: RequestInit;
}

function jsonResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

function problemResponse(status: number, problem: Record<string, unknown>, headers: Record<string, string> = {}) {
  return new Response(JSON.stringify(problem), {
    status,
    headers: { 'Content-Type': 'application/problem+json', ...headers },
  });
}

// Servidor falso: responde en orden y simula la cookie que deja /sanctum/csrf-cookie.
function fakeServer(responses: (() => Response)[]) {
  const calls: Call[] = [];
  let cookies = '';
  let index = 0;

  const fetch = vi.fn((input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    calls.push({ url, init });

    if (url === '/sanctum/csrf-cookie') {
      cookies = `XSRF-TOKEN=${encodeURIComponent(`token+${String(calls.length)}=`)}; laravel_session=abc`;

      return Promise.resolve(new Response(null, { status: 204 }));
    }

    const next = responses[index];
    index += 1;

    if (next === undefined) {
      return Promise.reject(new Error(`Petición inesperada: ${url}`));
    }

    return Promise.resolve(next());
  });

  const client = createApiClient({
    fetch: fetch as unknown as typeof globalThis.fetch,
    readCookies: () => cookies,
  });

  return {
    client,
    calls,
    setCookies: (value: string) => {
      cookies = value;
    },
  };
}

function header(call: Call | undefined, name: string): string | null {
  return new Headers(call?.init.headers).get(name);
}

const userSchema = z.object({ data: z.object({ id: z.number(), email: z.string() }) });

describe('cliente HTTP', () => {
  it('valida la respuesta con el esquema y devuelve el dato tipado', async () => {
    const { client, calls } = fakeServer([() => jsonResponse(200, { data: { id: 1, email: 'a@wms.test' } })]);

    const result = await client.request('GET', '/auth/me', { schema: userSchema });

    expect(result.data.email).toBe('a@wms.test');
    expect(calls).toHaveLength(1);
    expect(calls[0]?.url).toBe('/api/v1/auth/me');
    expect(calls[0]?.init.credentials).toBe('same-origin');
    expect(header(calls[0], 'Accept')).toBe('application/json');
    expect(header(calls[0], 'X-XSRF-TOKEN')).toBeNull();
  });

  it('obtiene la cookie CSRF antes de la primera petición que modifica datos y envía el token decodificado', async () => {
    const { client, calls } = fakeServer([() => new Response(null, { status: 204 })]);

    await client.request('POST', '/auth/logout');

    expect(calls.map((call) => call.url)).toEqual(['/sanctum/csrf-cookie', '/api/v1/auth/logout']);
    expect(header(calls[1], 'X-XSRF-TOKEN')).toBe('token+1=');
  });

  it('no vuelve a pedir la cookie CSRF si ya existe', async () => {
    const { client, calls, setCookies } = fakeServer([() => new Response(null, { status: 204 })]);
    setCookies('XSRF-TOKEN=existente');

    await client.request('POST', '/auth/logout');

    expect(calls.map((call) => call.url)).toEqual(['/api/v1/auth/logout']);
    expect(header(calls[0], 'X-XSRF-TOKEN')).toBe('existente');
  });

  it('comparte una sola obtención de la cookie CSRF entre peticiones simultáneas', async () => {
    const { client, calls } = fakeServer([
      () => new Response(null, { status: 204 }),
      () => new Response(null, { status: 204 }),
    ]);

    await Promise.all([client.request('POST', '/a'), client.request('POST', '/b')]);

    expect(calls.filter((call) => call.url === '/sanctum/csrf-cookie')).toHaveLength(1);
  });

  it('ante un 419 renueva la cookie CSRF y reintenta una sola vez', async () => {
    const { client, calls, setCookies } = fakeServer([
      () => problemResponse(419, { code: 'CSRF_TOKEN_MISMATCH', status: 419 }),
      () => new Response(null, { status: 204 }),
    ]);
    setCookies('XSRF-TOKEN=vencido');

    await client.request('POST', '/auth/logout');

    expect(calls.map((call) => call.url)).toEqual([
      '/api/v1/auth/logout',
      '/sanctum/csrf-cookie',
      '/api/v1/auth/logout',
    ]);
    expect(header(calls[2], 'X-XSRF-TOKEN')).toBe('token+2=');
  });

  it('no reintenta un segundo 419', async () => {
    const { client } = fakeServer([
      () => problemResponse(419, { code: 'CSRF_TOKEN_MISMATCH' }),
      () => problemResponse(419, { code: 'CSRF_TOKEN_MISMATCH' }),
    ]);

    await expect(client.request('POST', '/auth/logout')).rejects.toMatchObject({
      code: 'CSRF_TOKEN_MISMATCH',
      status: 419,
    });
  });

  it('convierte Problem Details en ApiError con code, errores por campo y Retry-After', async () => {
    const { client } = fakeServer([
      () =>
        problemResponse(
          422,
          {
            type: 'about:blank',
            title: 'Unprocessable Content',
            status: 422,
            detail: 'The email field is required.',
            instance: '/api/v1/auth/login',
            code: 'VALIDATION_FAILED',
            errors: { email: ['El campo correo es obligatorio.'] },
          },
          { 'Retry-After': '30' },
        ),
    ]);

    const error: unknown = await client.request('POST', '/auth/login', { body: {} }).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({
      status: 422,
      code: 'VALIDATION_FAILED',
      instance: '/api/v1/auth/login',
      fieldErrors: { email: ['El campo correo es obligatorio.'] },
      retryAfterSeconds: 30,
      isRetryable: false,
    });
    expect(problemMessage(error as ApiError)).toBe('Los datos ingresados no son válidos.');
  });

  it('asigna el code por estado cuando la respuesta de error no es Problem Details', async () => {
    const { client } = fakeServer([
      () => new Response('<html>Bad Gateway</html>', { status: 502, statusText: 'Bad Gateway' }),
    ]);

    await expect(client.request('GET', '/health')).rejects.toMatchObject({
      status: 502,
      code: 'INTERNAL_ERROR',
      isRetryable: true,
    });
  });

  it('traduce una falla de red a NETWORK_ERROR reintentable', async () => {
    const fetch = vi.fn(() => Promise.reject(new TypeError('Failed to fetch')));
    const client = createApiClient({ fetch: fetch as unknown as typeof globalThis.fetch, readCookies: () => '' });

    await expect(client.request('GET', '/health')).rejects.toMatchObject({
      status: 0,
      code: 'NETWORK_ERROR',
      isRetryable: true,
    });
  });

  it('propaga la cancelación sin convertirla en ApiError', async () => {
    const fetch = vi.fn(() => Promise.reject(new DOMException('Aborted', 'AbortError')));
    const client = createApiClient({ fetch: fetch as unknown as typeof globalThis.fetch, readCookies: () => '' });

    await expect(client.request('GET', '/health')).rejects.toMatchObject({ name: 'AbortError' });
  });

  it('rechaza una respuesta que no cumple el esquema como INVALID_RESPONSE', async () => {
    const { client } = fakeServer([() => jsonResponse(200, { data: { id: 'uno' } })]);

    await expect(client.request('GET', '/auth/me', { schema: userSchema })).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
    });
  });

  it('envía el idioma activo de la interfaz en Accept-Language', async () => {
    const fetch = vi.fn(() => Promise.resolve(new Response(null, { status: 204 })));
    const client = createApiClient({
      fetch: fetch as unknown as typeof globalThis.fetch,
      readCookies: () => '',
      language: () => 'es',
    });

    await client.request('GET', '/health');

    const init = (fetch.mock.calls[0] as unknown as [string, RequestInit])[1];

    expect(new Headers(init.headers).get('Accept-Language')).toBe('es');
  });

  it('envía el cuerpo como JSON y la Idempotency-Key recibida', async () => {
    const { client, calls, setCookies } = fakeServer([() => new Response(null, { status: 204 })]);
    setCookies('XSRF-TOKEN=t');

    await client.request('POST', '/inventory/transfers', {
      body: { quantity: 5 },
      idempotencyKey: '7f1d1d0e-1111-4c4c-8888-123456789abc',
    });

    expect(header(calls[0], 'Content-Type')).toBe('application/json');
    expect(header(calls[0], 'Idempotency-Key')).toBe('7f1d1d0e-1111-4c4c-8888-123456789abc');
    expect(calls[0]?.init.body).toBe('{"quantity":5}');
  });
});
