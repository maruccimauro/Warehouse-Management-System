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
  // Preferencias guardadas en users (E2.21). locale puede ser un idioma que el backend
  // admite y la interfaz todavía no: se aplica solo si existe su traducción.
  locale: z.string(),
  show_technical_names: z.boolean(),
  // Con true, la SPA muestra solo el cambio de contraseña (REG-021).
  password_change_required: z.boolean(),
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

export interface PasswordChange {
  current_password: string;
  password: string;
  password_confirmation: string;
}

// Cierra las demás sesiones del usuario y conserva la actual (REG-020, REG-021).
export function changePassword(change: PasswordChange): Promise<void> {
  return api.request('PUT', '/me/password', { body: change });
}
