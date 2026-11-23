import { createContext, use } from 'react';
import { z } from 'zod';

// Preferencias por usuario en este navegador hasta E2, cuando pasan al servidor junto con
// users.locale (ADR-016). Cada campo tiene su valor por defecto: un dato guardado inválido
// o de una versión anterior no rompe la interfaz.
const preferencesSchema = z.object({
  // Parámetros de usuario de SU3. LGN: código del depósito por defecto.
  parameters: z.object({ LGN: z.string().optional() }).catch({}),
  favorites: z.array(z.string()).catch([]),
  showTechnicalNames: z.boolean().catch(false),
  commandHistory: z.array(z.string()).catch([]),
});

export type UserPreferences = z.infer<typeof preferencesSchema>;
export type ParameterId = keyof UserPreferences['parameters'];

export const defaultPreferences: UserPreferences = {
  parameters: {},
  favorites: [],
  showTechnicalNames: false,
  commandHistory: [],
};

const maxHistory = 10;

function storageKey(userId: number): string {
  return `wms.preferences.${userId}`;
}

export function readPreferences(userId: number): UserPreferences {
  try {
    const raw = localStorage.getItem(storageKey(userId));

    if (raw === null) {
      return defaultPreferences;
    }

    const parsed = preferencesSchema.safeParse(JSON.parse(raw));

    return parsed.success ? parsed.data : defaultPreferences;
  } catch {
    // Almacenamiento bloqueado o JSON corrupto.
    return defaultPreferences;
  }
}

export function writePreferences(userId: number, preferences: UserPreferences): void {
  try {
    localStorage.setItem(storageKey(userId), JSON.stringify(preferences));
  } catch {
    // Sin almacenamiento las preferencias duran lo que dure la pestaña.
  }
}

export function withHistoryEntry(history: readonly string[], code: string): string[] {
  return [code, ...history.filter((entry) => entry !== code)].slice(0, maxHistory);
}

export interface UserPreferencesContextValue {
  preferences: UserPreferences;
  setParameter: (id: ParameterId, value: string | undefined) => void;
  toggleFavorite: (code: string) => boolean;
  setShowTechnicalNames: (show: boolean) => void;
  addToHistory: (code: string) => void;
}

export const UserPreferencesContext = createContext<UserPreferencesContextValue | undefined>(undefined);

export function useUserPreferences(): UserPreferencesContextValue {
  const value = use(UserPreferencesContext);

  if (value === undefined) {
    throw new Error('useUserPreferences se usó fuera de UserPreferencesProvider.');
  }

  return value;
}
