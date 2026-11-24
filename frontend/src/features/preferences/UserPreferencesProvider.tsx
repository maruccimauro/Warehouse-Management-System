import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import type { AuthenticatedUser } from '@/features/auth/authApi';
import { sessionQueryKey } from '@/features/auth/session';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { i18n } from '@/i18n';
import { fallbackLanguage, isSupportedLanguage } from '@/i18n/resources';
import { ApiError } from '@/shared/api/ApiError';
import { problemMessage } from '@/shared/errors/problemMessage';
import { Button } from '@/shared/ui/Button';
import {
  readCommandHistory,
  readLegacyPreferences,
  removeLegacyPreferences,
  withHistoryEntry,
  writeCommandHistory,
} from './localPreferences';
import {
  favoritesQueryKey,
  favoritesQueryOptions,
  maxFavorites,
  parametersQueryKey,
  parametersQueryOptions,
  saveFavorites,
  saveParameters,
  saveSettings,
  transactionCodePattern,
  type UserParameters,
  type UserSettings,
} from './preferencesApi';
import { UserPreferencesContext } from './userPreferences';

const statusClass = 'mx-auto mt-[10dvh] max-w-lg rounded-sm border border-border bg-surface p-4';

function setSessionUser(queryClient: QueryClient, user: AuthenticatedUser): void {
  queryClient.setQueryData(sessionQueryKey, user);
}

// Sube una sola vez lo que E1 guardó en el navegador (D8): cada preferencia solo completa
// lo que el servidor tiene vacío, así no pisa lo que el usuario ya grabó desde otro equipo.
// Si alguna subida falla, la clave local queda y se reintenta en la próxima sesión.
async function migrateLegacyPreferences(
  queryClient: QueryClient,
  user: AuthenticatedUser,
  parameters: UserParameters,
  favorites: readonly string[],
): Promise<void> {
  const legacy = readLegacyPreferences(user.id);

  if (legacy === undefined) {
    return;
  }

  const uploads: Promise<unknown>[] = [];
  const legacyLgn = legacy.parameters.LGN;

  if (parameters.LGN === null && legacyLgn !== undefined && user.warehouses.some((warehouse) => warehouse.code === legacyLgn)) {
    uploads.push(
      saveParameters({ ...parameters, LGN: legacyLgn }).then((saved) => queryClient.setQueryData(parametersQueryKey, saved)),
    );
  }

  const legacyFavorites = [...new Set(legacy.favorites.filter((code) => transactionCodePattern.test(code)))].slice(0, maxFavorites);

  if (favorites.length === 0 && legacyFavorites.length > 0) {
    uploads.push(saveFavorites(legacyFavorites).then((saved) => queryClient.setQueryData(favoritesQueryKey, saved)));
  }

  if (!user.show_technical_names && legacy.showTechnicalNames) {
    uploads.push(saveSettings({ show_technical_names: true }).then((saved) => {
      setSessionUser(queryClient, saved);
    }));
  }

  await Promise.all(uploads);
  removeLegacyPreferences(user.id);
}

function PreferencesStatus({ error, onRetry }: { error: Error | null; onRetry: () => void }) {
  const { t } = useTranslation();

  if (error === null) {
    return (
      <p role="status" className={statusClass}>
        {t('status.loadingPreferences')}
      </p>
    );
  }

  return (
    <div role="alert" className={`${statusClass} grid gap-2 text-error`}>
      <p>{error instanceof ApiError ? problemMessage(error) : t('codes.UNKNOWN', { ns: 'errors' })}</p>
      <Button className="w-fit" onClick={onRetry}>
        {t('actions.retry')}
      </Button>
    </div>
  );
}

// Se monta con key={userId}: otro usuario en el mismo navegador parte de sus propias
// preferencias. Espera parámetros y favoritos antes de mostrar el armazón: una pantalla
// como SU3 toma de ahí su valor inicial y no debe arrancar con uno provisorio.
export function UserPreferencesProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation('errors');
  const user = useAuthenticatedUser();
  const queryClient = useQueryClient();
  const { show } = useStatusBar();
  const parameters = useQuery(parametersQueryOptions);
  const favorites = useQuery(favoritesQueryOptions);
  const [commandHistory, setCommandHistory] = useState(() => readCommandHistory(user.id));
  const migrated = useRef(false);

  useEffect(() => {
    writeCommandHistory(user.id, commandHistory);
  }, [user.id, commandHistory]);

  // Idioma del usuario (users.locale). Uno que la interfaz todavía no tiene traducido
  // deja el idioma por defecto (D7).
  useEffect(() => {
    void i18n.changeLanguage(isSupportedLanguage(user.locale) ? user.locale : fallbackLanguage);
  }, [user.locale]);

  useEffect(() => {
    if (migrated.current || parameters.data === undefined || favorites.data === undefined) {
      return;
    }

    migrated.current = true;
    migrateLegacyPreferences(queryClient, user, parameters.data, favorites.data).catch(() => {
      // Se reintenta en la próxima sesión: la clave local no se borró.
    });
  }, [queryClient, user, parameters.data, favorites.data]);

  const reportFailure = (error: unknown) => {
    show(errorMessage(error, t('codes.UNKNOWN')));
  };

  // Una grabación rechazada vuelve a leer del servidor en lugar de revertir a mano: con
  // varios cambios seguidos, el valor anterior en memoria puede no ser el grabado.
  const favoritesMutation = useMutation({
    mutationFn: saveFavorites,
    scope: { id: 'favorites' },
    onSuccess: (saved) => queryClient.setQueryData(favoritesQueryKey, saved),
    onError: (error) => {
      reportFailure(error);
      void queryClient.invalidateQueries({ queryKey: favoritesQueryKey });
    },
  });

  const technicalNamesMutation = useMutation({
    mutationFn: (show: boolean) => saveSettings({ show_technical_names: show }),
    scope: { id: 'settings' },
    onSuccess: (saved) => {
      setSessionUser(queryClient, saved);
    },
    onError: (error) => {
      reportFailure(error);
      void queryClient.invalidateQueries({ queryKey: sessionQueryKey });
    },
  });

  if (parameters.data === undefined || favorites.data === undefined) {
    return (
      <PreferencesStatus
        error={parameters.error ?? favorites.error}
        onRetry={() => {
          void parameters.refetch();
          void favorites.refetch();
        }}
      />
    );
  }

  const currentFavorites = favorites.data;

  const toggleFavorite = (code: string): boolean => {
    const isFavorite = currentFavorites.includes(code);
    const next = isFavorite ? currentFavorites.filter((favorite) => favorite !== code) : [...currentFavorites, code];

    queryClient.setQueryData(favoritesQueryKey, next);
    favoritesMutation.mutate(next);

    return !isFavorite;
  };

  const setShowTechnicalNames = (showCodes: boolean) => {
    setSessionUser(queryClient, { ...user, show_technical_names: showCodes });
    technicalNamesMutation.mutate(showCodes);
  };

  const saveOwnParameters = async (next: UserParameters) => {
    queryClient.setQueryData(parametersQueryKey, await saveParameters(next));
  };

  const saveOwnSettings = async (settings: UserSettings) => {
    setSessionUser(queryClient, await saveSettings(settings));
  };

  const addToHistory = (code: string) => {
    setCommandHistory((current) => withHistoryEntry(current, code));
  };

  return (
    <UserPreferencesContext
      value={{
        preferences: {
          parameters: parameters.data,
          favorites: currentFavorites,
          showTechnicalNames: user.show_technical_names,
          commandHistory,
        },
        saveParameters: saveOwnParameters,
        saveSettings: saveOwnSettings,
        toggleFavorite,
        setShowTechnicalNames,
        addToHistory,
      }}
    >
      {children}
    </UserPreferencesContext>
  );
}
