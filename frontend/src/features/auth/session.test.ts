import { createQueryClient } from '@/app/queryClient';
import { ApiError } from '@/shared/api/ApiError';
import { sessionQueryKey } from './session';
import { testUser } from '@/test/fakeApi';

describe('sesión expirada', () => {
  it('un 401 en cualquier consulta termina la sesión y descarta los datos cacheados', async () => {
    const queryClient = createQueryClient();
    queryClient.setQueryData(sessionQueryKey, testUser());
    queryClient.setQueryData(['products'], ['datos del usuario anterior']);

    await expect(
      queryClient.query({
        queryKey: ['stock'],
        queryFn: () => Promise.reject(new ApiError({ status: 401, code: 'UNAUTHENTICATED' })),
      }),
    ).rejects.toBeInstanceOf(ApiError);

    expect(queryClient.getQueryData(sessionQueryKey)).toBeNull();
    expect(queryClient.getQueryData(['products'])).toBeUndefined();
  });

  it('otros errores no afectan la sesión', async () => {
    const queryClient = createQueryClient();
    const user = testUser();
    queryClient.setQueryData(sessionQueryKey, user);

    await expect(
      queryClient.query({
        queryKey: ['stock'],
        queryFn: () => Promise.reject(new ApiError({ status: 403, code: 'FORBIDDEN' })),
      }),
    ).rejects.toBeInstanceOf(ApiError);

    expect(queryClient.getQueryData(sessionQueryKey)).toEqual(user);
  });
});
