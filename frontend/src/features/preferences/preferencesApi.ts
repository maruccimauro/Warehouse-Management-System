import { queryOptions } from '@tanstack/react-query';
import { z } from 'zod';
import { authenticatedUserSchema, type AuthenticatedUser } from '@/features/auth/authApi';
import { api } from '@/shared/api/apiClient';

// Preferencias del usuario en el servidor (E2.21, ADR-016). Parámetros y favoritos se
// graban como reemplazo completo; idioma y mostrar códigos viven en users (PATCH /me).
export const parametersSchema = z.object({
  // Parámetros de usuario de SU3. LGN: código del depósito por defecto.
  LGN: z.string().nullable(),
});

export type UserParameters = z.infer<typeof parametersSchema>;
export type ParameterId = keyof UserParameters;

export interface UserSettings {
  locale?: string;
  show_technical_names?: boolean;
}

// Mismo formato que el campo de comando y que el CHECK de user_favorites.
export const transactionCodePattern = /^[A-Z][A-Z0-9_]{1,19}$/;
export const maxFavorites = 50;

const parametersResponse = z.object({ data: parametersSchema }).transform((response) => response.data);
const favoritesResponse = z.object({ data: z.array(z.string()) }).transform((response) => response.data);
const userResponse = z.object({ data: authenticatedUserSchema }).transform((response) => response.data);

export const parametersQueryKey = ['me', 'parameters'] as const;
export const favoritesQueryKey = ['me', 'favorites'] as const;

export const parametersQueryOptions = queryOptions({
  queryKey: parametersQueryKey,
  queryFn: ({ signal }) => api.request('GET', '/me/parameters', { schema: parametersResponse, signal }),
  // Cambian solo desde esta sesión: no hace falta volver a pedirlas.
  staleTime: Infinity,
});

export const favoritesQueryOptions = queryOptions({
  queryKey: favoritesQueryKey,
  queryFn: ({ signal }) => api.request('GET', '/me/favorites', { schema: favoritesResponse, signal }),
  staleTime: Infinity,
});

export function saveParameters(parameters: UserParameters): Promise<UserParameters> {
  return api.request('PUT', '/me/parameters', { body: parameters, schema: parametersResponse });
}

export function saveFavorites(favorites: readonly string[]): Promise<string[]> {
  return api.request('PUT', '/me/favorites', { body: { favorites }, schema: favoritesResponse });
}

export function saveSettings(settings: UserSettings): Promise<AuthenticatedUser> {
  return api.request('PATCH', '/me', { body: settings, schema: userResponse });
}
