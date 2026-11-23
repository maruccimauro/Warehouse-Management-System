import { createContext, use } from 'react';
import { ApiError } from '@/shared/api/ApiError';
import { problemMessage } from '@/shared/errors/problemMessage';

// Tipos de mensaje de SAP: S éxito, W advertencia, E error, I información (ADR-016).
export type MessageType = 'S' | 'W' | 'E' | 'I';

export interface StatusMessage {
  type: MessageType;
  text: string;
  // Identificador estable del mensaje: el code del Problem Details (ADR-010).
  id?: string | undefined;
}

export interface StatusBarContextValue {
  message: StatusMessage | undefined;
  show: (message: StatusMessage) => void;
  clear: () => void;
}

export const StatusBarContext = createContext<StatusBarContextValue | undefined>(undefined);

export function useStatusBar(): StatusBarContextValue {
  const value = use(StatusBarContext);

  if (value === undefined) {
    throw new Error('useStatusBar se usó fuera de StatusBarProvider.');
  }

  return value;
}

export function errorMessage(error: unknown, fallback: string): StatusMessage {
  if (error instanceof ApiError) {
    return { type: 'E', text: problemMessage(error), id: error.code };
  }

  return { type: 'E', text: fallback };
}
