import { useEffect, useState, type ReactNode } from 'react';
import {
  readPreferences,
  UserPreferencesContext,
  withHistoryEntry,
  writePreferences,
  type ParameterId,
  type UserPreferences,
} from './userPreferences';

interface UserPreferencesProviderProps {
  userId: number;
  children: ReactNode;
}

// Se monta con key={userId}: otro usuario en el mismo navegador parte de sus propias preferencias.
export function UserPreferencesProvider({ userId, children }: UserPreferencesProviderProps) {
  const [preferences, setPreferences] = useState<UserPreferences>(() => readPreferences(userId));

  useEffect(() => {
    writePreferences(userId, preferences);
  }, [userId, preferences]);

  const setParameter = (id: ParameterId, value: string | undefined) => {
    setPreferences((current) => {
      const parameters = { ...current.parameters };

      if (value === undefined) {
        // eslint-disable-next-line @typescript-eslint/no-dynamic-delete -- id es una clave tipada de parameters
        delete parameters[id];
      } else {
        parameters[id] = value;
      }

      return { ...current, parameters };
    });
  };

  // Devuelve si la transacción quedó como favorita.
  const toggleFavorite = (code: string): boolean => {
    const isFavorite = preferences.favorites.includes(code);

    setPreferences((current) => ({
      ...current,
      favorites: isFavorite ? current.favorites.filter((favorite) => favorite !== code) : [...current.favorites, code],
    }));

    return !isFavorite;
  };

  const setShowTechnicalNames = (show: boolean) => {
    setPreferences((current) => ({ ...current, showTechnicalNames: show }));
  };

  const addToHistory = (code: string) => {
    setPreferences((current) => ({ ...current, commandHistory: withHistoryEntry(current.commandHistory, code) }));
  };

  return (
    <UserPreferencesContext value={{ preferences, setParameter, toggleFavorite, setShowTechnicalNames, addToHistory }}>
      {children}
    </UserPreferencesContext>
  );
}
