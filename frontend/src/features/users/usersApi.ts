import { z } from 'zod';
import { api } from '@/shared/api/apiClient';
import { listPageSchema, listPath, type ListPage, type ListParams } from '@/shared/api/listing';

// Usuarios y roles (módulo Identity del backend, E2.09 y E2.14). El código de usuario se guarda
// en mayúsculas y no cambia después del alta (users_username_format_check, D1); el ingreso
// sigue con el email. Los roles los define el backend y el nombre visible lo traduce la
// interfaz.

export const roleNames = ['administrator', 'supervisor', 'operator', 'purchasing', 'auditor'] as const;
export type RoleName = (typeof roleNames)[number];

// Límites que valida el backend y que la pantalla anticipa.
export const usernameMaxLength = 12;
export const usernamePattern = /^[A-Z0-9]{1,12}$/;
export const userNameMaxLength = 120;
export const emailMaxLength = 255;
export const passwordMinLength = 12;
export const passwordMaxLength = 255;

export function normalizeUsername(text: string): string {
  return text.trim().toUpperCase();
}

export function isRoleName(value: string): value is RoleName {
  return (roleNames as readonly string[]).includes(value);
}

const assignedWarehouseSchema = z.object({
  id: z.number().int(),
  code: z.string(),
  name: z.string(),
  is_active: z.boolean(),
});

export const userSchema = z.object({
  id: z.number().int(),
  username: z.string(),
  name: z.string(),
  email: z.string(),
  locale: z.string(),
  is_active: z.boolean(),
  password_change_required: z.boolean(),
  roles: z.array(z.string()),
  warehouses: z.array(assignedWarehouseSchema),
});

export const roleSchema = z.object({
  name: z.string(),
  permissions: z.array(z.string()),
});

export type User = z.infer<typeof userSchema>;
export type Role = z.infer<typeof roleSchema>;

const userPage = listPageSchema(userSchema);
const userResponse = z.object({ data: userSchema }).transform((response) => response.data);
const rolesResponse = z.object({ data: z.array(roleSchema) }).transform((response) => response.data);

export const usersQueryKey = ['users'] as const;
export const rolesQueryKey = ['roles'] as const;

export function fetchUsers(params: ListParams, signal?: AbortSignal): Promise<ListPage<User>> {
  return api.request('GET', listPath('/users', params), { schema: userPage, signal });
}

// Usuario por su código exacto, para las pantallas iniciales.
export async function findUser(username: string, signal?: AbortSignal): Promise<User | undefined> {
  const page = await fetchUsers({ page: 1, perPage: 1, filters: { username: [{ sign: 'I', option: 'EQ', low: username }] } }, signal);

  return page.items[0];
}

export interface UserInput {
  name?: string;
  email?: string;
  is_active?: boolean;
  roles?: string[];
  warehouse_ids?: number[];
  // Contraseña inicial: el usuario la cambia antes de operar (REG-021).
  password?: string;
}

export function createUser(input: UserInput & { username: string; name: string; email: string; password: string; roles: string[]; warehouse_ids: number[] }): Promise<User> {
  return api.request('POST', '/users', { body: input, schema: userResponse });
}

// La ficha se graba entera en un PATCH (DM-23): datos, estado, roles, depósitos y contraseña.
export function updateUser(id: number, input: UserInput): Promise<User> {
  return api.request('PATCH', `/users/${String(id)}`, { body: input, schema: userResponse });
}

export function fetchRoles(signal?: AbortSignal): Promise<Role[]> {
  return api.request('GET', '/roles', { schema: rolesResponse, signal });
}
