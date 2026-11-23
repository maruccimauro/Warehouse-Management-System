import { act, renderHook } from '@testing-library/react';
import { shouldRetryQuery } from '@/app/queryClient';
import { ApiError } from './ApiError';
import { useIdempotencyKey } from './idempotency';

describe('useIdempotencyKey', () => {
  it('conserva la clave entre renders del mismo intento', () => {
    const { result, rerender } = renderHook(() => useIdempotencyKey());
    const first = result.current.key;

    rerender();

    expect(result.current.key).toBe(first);
    expect(first).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  });

  it('genera una clave nueva al renovar el intento', () => {
    const { result } = renderHook(() => useIdempotencyKey());
    const first = result.current.key;

    act(() => {
      result.current.renew();
    });

    expect(result.current.key).not.toBe(first);
  });
});

describe('reintentos de consultas', () => {
  it('reintenta solo fallas transitorias y hasta dos veces', () => {
    const network = new ApiError({ status: 0, code: 'NETWORK_ERROR' });
    const conflict = new ApiError({ status: 503, code: 'CONCURRENCY_CONFLICT' });
    const notFound = new ApiError({ status: 404, code: 'NOT_FOUND' });

    expect(shouldRetryQuery(0, network)).toBe(true);
    expect(shouldRetryQuery(1, conflict)).toBe(true);
    expect(shouldRetryQuery(2, network)).toBe(false);
    expect(shouldRetryQuery(0, notFound)).toBe(false);
    expect(shouldRetryQuery(0, new TypeError('defecto de programación'))).toBe(false);
  });
});
