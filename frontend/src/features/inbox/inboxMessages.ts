import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import type { InboxMessage } from './inboxApi';

// Tipos de aviso que la interfaz sabe mostrar (E3.27). Cada uno tiene su texto en
// inbox.json (types.<entidad>.<evento>, con los parámetros del mensaje) y, si el aviso se
// refiere a un objeto, la transacción que lo abre desde la bandeja. Un tipo que todavía no
// está acá (un backend más nuevo que la interfaz) se muestra con un texto genérico.
interface InboxMessageType {
  link?: (message: InboxMessage) => string | undefined;
}

export const inboxMessageTypes: Readonly<Record<string, InboxMessageType>> = {};

export function parameterText(message: InboxMessage, name: string): string {
  const value = message.parameters[name];

  return value === null || value === undefined ? '' : String(value);
}

export function inboxMessageLink(message: InboxMessage): string | undefined {
  return inboxMessageTypes[message.type]?.link?.(message);
}

export function useInboxMessageText() {
  const { t } = useTranslation('inbox');

  // Estable entre renders: las columnas de la grilla dependen de ella.
  return useCallback((message: InboxMessage, parameters: Record<string, string> = {}): string => {
    if (!Object.hasOwn(inboxMessageTypes, message.type)) {
      return t('types.unknown', { type: message.type });
    }

    const values = Object.fromEntries(Object.keys(message.parameters).map((name) => [name, parameterText(message, name)]));

    // La clave sale del registro de tipos (types.<entidad>.<evento>) y los parámetros del
    // mensaje: no se conocen al compilar, por eso la llamada no usa las claves tipadas.
    const translate = t as unknown as (key: string, options: Record<string, string>) => string;

    return translate(`types.${message.type}`, { ...values, ...parameters });
  }, [t]);
}
