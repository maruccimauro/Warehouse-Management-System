import type { z } from 'zod';
import { i18n } from '@/i18n';
import { ApiError, codeForStatus, problemDetailsSchema } from './ApiError';
import { readXsrfToken } from './csrf';

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

const stateChangingMethods: ReadonlySet<HttpMethod> = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

export interface RequestOptions {
  body?: unknown;
  idempotencyKey?: string;
  signal?: AbortSignal;
}

export interface RequestOptionsWithSchema<S extends z.ZodType> extends RequestOptions {
  // Esquema de la respuesta: el tipo devuelto sale de la validación, no de una aserción.
  schema: S;
}

export interface ApiClientConfig {
  baseUrl?: string;
  csrfCookieUrl?: string;
  fetch?: typeof fetch;
  readCookies?: () => string;
  // Idioma activo de la interfaz: el backend responde en ese idioma (SetLocale), no en el
  // que tenga configurado el navegador.
  language?: () => string | undefined;
}

export interface ApiClient {
  request<S extends z.ZodType>(
    method: HttpMethod,
    path: string,
    options: RequestOptionsWithSchema<S>,
  ): Promise<z.infer<S>>;
  request(method: HttpMethod, path: string, options?: RequestOptions): Promise<void>;
  ensureCsrfCookie(): Promise<void>;
}

// Retry-After admite segundos o una fecha HTTP (RFC 9110).
function parseRetryAfter(header: string | null): number | undefined {
  if (header === null || header.trim() === '') {
    return undefined;
  }

  const seconds = Number(header);

  if (Number.isFinite(seconds)) {
    return Math.max(0, Math.ceil(seconds));
  }

  const date = Date.parse(header);

  return Number.isNaN(date) ? undefined : Math.max(0, Math.ceil((date - Date.now()) / 1000));
}

async function readJson(response: Response): Promise<unknown> {
  try {
    return (await response.json()) as unknown;
  } catch {
    return undefined;
  }
}

async function errorFromResponse(response: Response): Promise<ApiError> {
  const retryAfter = parseRetryAfter(response.headers.get('Retry-After'));
  const problem = problemDetailsSchema.safeParse(await readJson(response));

  if (problem.success) {
    return ApiError.fromProblem(problem.data, response.status, retryAfter);
  }

  return new ApiError({
    status: response.status,
    code: codeForStatus(response.status),
    title: response.statusText === '' ? undefined : response.statusText,
    retryAfterSeconds: retryAfter,
  });
}

function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

export function createApiClient(config: ApiClientConfig = {}): ApiClient {
  const baseUrl = config.baseUrl ?? '/api/v1';
  const csrfCookieUrl = config.csrfCookieUrl ?? '/sanctum/csrf-cookie';
  const fetchFn = config.fetch ?? globalThis.fetch.bind(globalThis);
  const readCookies = config.readCookies ?? (() => document.cookie);
  const language = config.language ?? (() => undefined);

  // Peticiones simultáneas comparten una sola obtención de la cookie CSRF.
  let pendingCsrf: Promise<void> | undefined;

  async function send(input: string, init: RequestInit): Promise<Response> {
    try {
      // Mismo origen (ADR-009): la cookie de sesión viaja sin CORS.
      return await fetchFn(input, { ...init, credentials: 'same-origin' });
    } catch (error) {
      // La cancelación no es un error de la API: se propaga para que TanStack Query la reconozca.
      if (isAbortError(error)) {
        throw error;
      }

      throw new ApiError({ status: 0, code: 'NETWORK_ERROR', cause: error });
    }
  }

  function fetchCsrfCookie(): Promise<void> {
    pendingCsrf ??= send(csrfCookieUrl, { headers: { Accept: 'application/json' } })
      .then(async (response) => {
        if (!response.ok) {
          throw await errorFromResponse(response);
        }
      })
      .finally(() => {
        pendingCsrf = undefined;
      });

    return pendingCsrf;
  }

  async function ensureCsrfCookie(): Promise<void> {
    if (readXsrfToken(readCookies()) === undefined) {
      await fetchCsrfCookie();
    }
  }

  async function execute(
    method: HttpMethod,
    path: string,
    options: RequestOptions,
    isRetry: boolean,
  ): Promise<Response> {
    const headers = new Headers({ Accept: 'application/json' });
    const init: RequestInit = { method, headers };
    const currentLanguage = language();

    if (currentLanguage !== undefined) {
      headers.set('Accept-Language', currentLanguage);
    }

    if (options.body !== undefined) {
      headers.set('Content-Type', 'application/json');
      init.body = JSON.stringify(options.body);
    }

    if (options.idempotencyKey !== undefined) {
      headers.set('Idempotency-Key', options.idempotencyKey);
    }

    if (options.signal !== undefined) {
      init.signal = options.signal;
    }

    if (stateChangingMethods.has(method)) {
      await ensureCsrfCookie();
      const token = readXsrfToken(readCookies());

      if (token !== undefined) {
        headers.set('X-XSRF-TOKEN', token);
      }
    }

    const response = await send(`${baseUrl}${path}`, init);

    // Token expirado o sesión renovada en otra pestaña: el servidor rechazó la petición antes
    // de procesarla, así que se renueva la cookie y se reintenta una sola vez.
    if (response.status === 419 && !isRetry) {
      await fetchCsrfCookie();

      return execute(method, path, options, true);
    }

    if (!response.ok) {
      throw await errorFromResponse(response);
    }

    return response;
  }

  async function request(
    method: HttpMethod,
    path: string,
    options: RequestOptions & { schema?: z.ZodType } = {},
  ): Promise<unknown> {
    const response = await execute(method, path, options, false);

    if (options.schema === undefined) {
      return undefined;
    }

    const parsed = options.schema.safeParse(await readJson(response));

    if (!parsed.success) {
      // El backend no cumple el contrato: es un defecto, no un error del usuario.
      throw new ApiError({ status: response.status, code: 'INVALID_RESPONSE', cause: parsed.error });
    }

    return parsed.data;
  }

  return { request: request as ApiClient['request'], ensureCsrfCookie };
}

// Instancia única de la aplicación.
export const api = createApiClient({ language: () => i18n.resolvedLanguage });
