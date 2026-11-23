import { z } from 'zod';
import { api } from '@/shared/api/apiClient';

// Contrato de AuthenticatedUserResource (ADR-009). warehouses son los depósitos activos
// donde el usuario opera (REG-005); los que solo consulta los lista la API de depósitos.
export const warehouseSummarySchema = z.object({
  id: z.number().int(),
  code: z.string(),
  name: z.string(),
});

export const authenticatedUserSchema = z.object({
  id: z.number().int(),
  name: z.string(),
  email: z.string(),
  roles: z.array(z.string()),
  permissions: z.array(z.string()),
  warehouses: z.array(warehouseSummarySchema),
});

export type WarehouseSummary = z.infer<typeof warehouseSummarySchema>;
export type AuthenticatedUser = z.infer<typeof authenticatedUserSchema>;

export interface Credentials {
  email: string;
  password: string;
}

// Los JsonResource de Laravel envuelven el recurso en data.
const userResponseSchema = z.object({ data: authenticatedUserSchema }).transform((response) => response.data);

export function fetchCurrentUser(signal?: AbortSignal): Promise<AuthenticatedUser> {
  return api.request('GET', '/auth/me', signal === undefined ? { schema: userResponseSchema } : { schema: userResponseSchema, signal });
}

export function login(credentials: Credentials): Promise<AuthenticatedUser> {
  return api.request('POST', '/auth/login', { body: credentials, schema: userResponseSchema });
}

export function logout(): Promise<void> {
  return api.request('POST', '/auth/logout');
}
