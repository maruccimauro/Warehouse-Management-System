import { createContext, use } from 'react';
import type { UserParameters, UserSettings } from './preferencesApi';

// Preferencias del usuario (ADR-016). Parámetros, favoritos, idioma y mostrar códigos
// viven en el servidor (E2.21); el historial del campo de comando, en el navegador.
export interface UserPreferences {
  parameters: UserParameters;
  favorites: readonly string[];
  showTechnicalNames: boolean;
  commandHistory: readonly string[];
}

export interface UserPreferencesContextValue {
  preferences: UserPreferences;
  // Grabación explícita (SU3): la pantalla informa el resultado.
  saveParameters: (parameters: UserParameters) => Promise<void>;
  saveSettings: (settings: UserSettings) => Promise<void>;
  // Cambios inmediatos con actualización optimista: si el servidor los rechaza se
  // revierten y el error sale en la barra de estado. Devuelve si quedó como favorita.
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
