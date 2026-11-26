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
  // Crece con cada mensaje mostrado, aunque repita el anterior: la barra lo usa para volver
  // a animar el aviso.
  sequence: number;
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

// Error que ya trae su mensaje para la barra de estado: una pantalla lo lanza desde onSave
// cuando un control propio impide grabar (por ejemplo, una plantilla que supera el límite).
export class StatusMessageError extends Error {
  readonly statusMessage: StatusMessage;

  constructor(statusMessage: StatusMessage) {
    super(statusMessage.text);
    this.name = 'StatusMessageError';
    this.statusMessage = statusMessage;
  }
}

export function errorMessage(error: unknown, fallback: string): StatusMessage {
  if (error instanceof StatusMessageError) {
    return error.statusMessage;
  }

  if (error instanceof ApiError) {
    return { type: 'E', text: problemMessage(error), id: error.code };
  }

  return { type: 'E', text: fallback };
}
