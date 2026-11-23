import { i18n } from '@/i18n';
import esErrors from '@/i18n/locales/es/errors.json';

export type ErrorCode = keyof typeof esErrors.codes;

// Subconjunto de Problem Details (ADR-010) que necesita el mensaje. El tipo ApiError
// completo, creado desde la respuesta HTTP, llega con el cliente HTTP (E1.09).
export interface ProblemLike {
  code?: string | undefined;
  detail?: string | undefined;
  context?: Record<string, unknown> | undefined;
}

// Códigos cuyo detail está escrito para el usuario y ya viene traducido por el backend:
// el mensaje de una policy explica por qué se deniega la acción concreta (ADR-010).
const detailFirstCodes: ReadonlySet<string> = new Set<ErrorCode>(['FORBIDDEN']);

export function isKnownErrorCode(code: string): code is ErrorCode {
  return Object.hasOwn(esErrors.codes, code);
}

// El cliente decide por code, nunca por el texto. Orden: traducción del code, detail del
// servidor como respaldo y, si no hay ninguno, el mensaje genérico. El context de los errores
// de dominio se expone a las traducciones como {{ctx.campo}}.
export function problemMessage(problem: ProblemLike): string {
  const { code, detail, context } = problem;

  if (code !== undefined && detailFirstCodes.has(code) && detail) {
    return detail;
  }

  if (code !== undefined && isKnownErrorCode(code)) {
    return i18n.t(`codes.${code}`, { ns: 'errors', ctx: context ?? {} });
  }

  return detail ?? i18n.t('codes.UNKNOWN', { ns: 'errors' });
}
