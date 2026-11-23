import { isKnownErrorCode, problemMessage } from './problemMessage';

// Códigos públicos de ADR-010 y del módulo Identity. Si el backend agrega un código
// transversal, esta lista obliga a traducirlo.
const backendCodes = [
  'VALIDATION_FAILED',
  'UNAUTHENTICATED',
  'FORBIDDEN',
  'NOT_FOUND',
  'METHOD_NOT_ALLOWED',
  'CSRF_TOKEN_MISMATCH',
  'TOO_MANY_REQUESTS',
  'INTERNAL_ERROR',
  'INVALID_CREDENTIALS',
  'SESSION_REQUIRED',
];

describe('problemMessage', () => {
  it.each(backendCodes)('tiene traducción para %s', (code) => {
    expect(isKnownErrorCode(code)).toBe(true);
  });

  it('traduce por code e ignora el detail del servidor', () => {
    expect(problemMessage({ code: 'INVALID_CREDENTIALS', detail: 'Invalid credentials.' })).toBe(
      'El correo electrónico o la contraseña no son correctos.',
    );
  });

  it('usa el detail como respaldo para un code sin traducción', () => {
    expect(problemMessage({ code: 'INSUFFICIENT_STOCK', detail: 'Stock insuficiente en la ubicación.' })).toBe(
      'Stock insuficiente en la ubicación.',
    );
  });

  it('usa el mensaje genérico cuando no hay code conocido ni detail', () => {
    expect(problemMessage({})).toBe('No fue posible completar la operación.');
  });

  it('prioriza el detail de FORBIDDEN porque es el mensaje de la policy', () => {
    expect(problemMessage({ code: 'FORBIDDEN', detail: 'El depósito está bloqueado para inventario.' })).toBe(
      'El depósito está bloqueado para inventario.',
    );
    expect(problemMessage({ code: 'FORBIDDEN' })).toBe('No tiene permisos para realizar esta acción.');
  });
});
