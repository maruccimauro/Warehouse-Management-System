import { vi } from 'vitest';
import type { AuthenticatedUser } from '@/features/auth/authApi';

export interface RecordedRequest {
  method: string;
  path: string;
  // Parámetros de la consulta (filtros, orden y página de un listado).
  query: URLSearchParams;
  headers: Headers;
  body: unknown;
}

type Handler = (request: RecordedRequest) => Response | Promise<Response>;

export const testXsrfToken = 'xsrf-de-prueba';

export function jsonResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

export function problemResponse(
  status: number,
  code: string,
  extra: Record<string, unknown> = {},
  headers: Record<string, string> = {},
): Response {
  return jsonResponse(status, { type: 'about:blank', title: code, status, code, ...extra }, {
    'Content-Type': 'application/problem+json',
    ...headers,
  });
}

export function testUser(overrides: Partial<AuthenticatedUser> = {}): AuthenticatedUser {
  return {
    id: 1,
    username: 'OPERADOR',
    name: 'Operador de Prueba',
    email: 'operador@wms.test',
    locale: 'es',
    show_technical_names: false,
    password_change_required: false,
    roles: [],
    permissions: [],
    warehouses: [],
    ...overrides,
  };
}

export function userResponse(user: AuthenticatedUser = testUser()): Response {
  return jsonResponse(200, { data: user });
}

function parseBody(init: RequestInit | undefined): unknown {
  return typeof init?.body === 'string' ? (JSON.parse(init.body) as unknown) : undefined;
}

export interface ServerPreferences {
  parameters: { LGN: string | null };
  favorites: string[];
}

// Doble de la API sobre fetch, por método y ruta ("POST /api/v1/auth/login"). Sin handler
// propio, la cookie CSRF se entrega como lo hace Sanctum y /auth/me responde sin sesión.
// Parámetros y favoritos se guardan en memoria durante la prueba, como en el servidor, y
// PATCH /me devuelve el usuario de /auth/me con los cambios aplicados.
export function stubApi(handlers: Record<string, Handler> = {}, initial: Partial<ServerPreferences> = {}) {
  const requests: RecordedRequest[] = [];
  const preferences: ServerPreferences = { parameters: { LGN: null }, favorites: [], ...initial };
  const defaults: Record<string, Handler> = {
    'GET /sanctum/csrf-cookie': () => {
      document.cookie = `XSRF-TOKEN=${testXsrfToken}; path=/`;

      return new Response(null, { status: 204 });
    },
    'GET /api/v1/auth/me': () => problemResponse(401, 'UNAUTHENTICATED'),
    'GET /api/v1/me/parameters': () => jsonResponse(200, { data: preferences.parameters }),
    'PUT /api/v1/me/parameters': (request) => {
      preferences.parameters = request.body as ServerPreferences['parameters'];

      return jsonResponse(200, { data: preferences.parameters });
    },
    // Contador de la bandeja del encabezado (E3.27): sin mensajes, salvo que la prueba lo defina.
    'GET /api/v1/me/inbox/unread-count': () => jsonResponse(200, { data: { unread: 0 } }),
    'GET /api/v1/me/favorites': () => jsonResponse(200, { data: preferences.favorites }),
    'PUT /api/v1/me/favorites': (request) => {
      preferences.favorites = (request.body as { favorites: string[] }).favorites;

      return jsonResponse(200, { data: preferences.favorites });
    },
    'PATCH /api/v1/me': async (request) => {
      const me = handlers['GET /api/v1/auth/me'];

      if (me === undefined) {
        return problemResponse(401, 'UNAUTHENTICATED');
      }

      const current = (await (await me(request)).json()) as { data: AuthenticatedUser };

      return userResponse({ ...current.data, ...(request.body as Partial<AuthenticatedUser>) });
    },
  };

  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const url = new URL(input instanceof Request ? input.url : String(input), 'http://localhost');
    const method = init?.method ?? 'GET';
    const request: RecordedRequest = {
      method,
      path: url.pathname,
      query: url.searchParams,
      headers: new Headers(init?.headers),
      body: parseBody(init),
    };
    const key = `${method} ${url.pathname}`;
    const handler = handlers[key] ?? defaults[key];

    requests.push(request);

    if (handler === undefined) {
      throw new Error(`Petición no prevista en la prueba: ${key}`);
    }

    return handler(request);
  });

  vi.stubGlobal('fetch', fetchMock);

  return {
    requests,
    preferences,
    requestsTo: (method: string, path: string) => requests.filter((r) => r.method === method && r.path === path),
  };
}
