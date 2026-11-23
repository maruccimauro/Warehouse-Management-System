import { z } from 'zod';

// Problem Details (RFC 9457) con las extensiones de ADR-010: code siempre presente,
// context en errores de dominio y errors por campo en VALIDATION_FAILED.
export const problemDetailsSchema = z.object({
  type: z.string().optional(),
  title: z.string().optional(),
  status: z.number().int().optional(),
  detail: z.string().optional(),
  instance: z.string().optional(),
  code: z.string().min(1),
  context: z.record(z.string(), z.unknown()).optional(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
});

export type ProblemDetails = z.infer<typeof problemDetailsSchema>;

// Estados que se pueden reintentar sin cambiar la petición: falla de red (0), servidor o
// proxy no disponibles, y conflicto de concurrencia agotado (503 CONCURRENCY_CONFLICT, ADR-004).
// Las operaciones que modifican stock solo se reintentan con la misma Idempotency-Key.
const retryableStatuses: ReadonlySet<number> = new Set([0, 502, 503, 504]);

interface ApiErrorInit {
  status: number;
  code: string;
  title?: string | undefined;
  detail?: string | undefined;
  type?: string | undefined;
  instance?: string | undefined;
  context?: Record<string, unknown> | undefined;
  fieldErrors?: Record<string, string[]> | undefined;
  retryAfterSeconds?: number | undefined;
  cause?: unknown;
}

export class ApiError extends Error {
  override readonly name = 'ApiError';
  readonly status: number;
  readonly code: string;
  readonly title: string | undefined;
  readonly detail: string | undefined;
  readonly type: string | undefined;
  readonly instance: string | undefined;
  readonly context: Record<string, unknown> | undefined;
  readonly fieldErrors: Record<string, string[]>;
  readonly retryAfterSeconds: number | undefined;

  constructor(init: ApiErrorInit) {
    // El message es para registros y depuración; la interfaz usa problemMessage(error).
    super(init.detail ?? init.title ?? init.code, { cause: init.cause });
    this.status = init.status;
    this.code = init.code;
    this.title = init.title;
    this.detail = init.detail;
    this.type = init.type;
    this.instance = init.instance;
    this.context = init.context;
    this.fieldErrors = init.fieldErrors ?? {};
    this.retryAfterSeconds = init.retryAfterSeconds;
  }

  get isRetryable(): boolean {
    return retryableStatuses.has(this.status);
  }

  static fromProblem(problem: ProblemDetails, status: number, retryAfterSeconds?: number): ApiError {
    return new ApiError({
      status,
      code: problem.code,
      title: problem.title,
      detail: problem.detail,
      type: problem.type,
      instance: problem.instance,
      context: problem.context,
      fieldErrors: problem.errors,
      retryAfterSeconds,
    });
  }
}

// Código para respuestas de error que no son Problem Details (por ejemplo, un 502 de Nginx
// con cuerpo HTML): el cliente sigue decidiendo por code en todos los casos.
export function codeForStatus(status: number): string {
  switch (status) {
    case 401:
      return 'UNAUTHENTICATED';
    case 403:
      return 'FORBIDDEN';
    case 404:
      return 'NOT_FOUND';
    case 405:
      return 'METHOD_NOT_ALLOWED';
    case 419:
      return 'CSRF_TOKEN_MISMATCH';
    case 429:
      return 'TOO_MANY_REQUESTS';
    default:
      return status >= 500 ? 'INTERNAL_ERROR' : 'UNKNOWN';
  }
}
