import { z } from 'zod';
import { api } from '@/shared/api/apiClient';
import { listPageSchema, listPath, type ListPage, type ListParams } from '@/shared/api/listing';

// Bandeja de entrada propia (módulo Inbox del backend, E3.27). El mensaje no trae texto: el
// tipo y los parámetros (una foto del momento del aviso) arman el texto en el idioma del
// usuario, como los mensajes de la barra de estado.

export type InboxFolder = 'inbox' | 'archived';

const parameterValue = z.union([z.string(), z.number(), z.boolean(), z.null()]);

export const inboxMessageSchema = z.object({
  id: z.number().int(),
  type: z.string(),
  parameters: z.record(z.string(), parameterValue),
  subject: z.object({ type: z.string(), id: z.number().int() }).nullable(),
  read_at: z.string().nullable(),
  archived_at: z.string().nullable(),
  created_at: z.string(),
});

export type InboxMessage = z.infer<typeof inboxMessageSchema>;

const inboxPage = listPageSchema(inboxMessageSchema);
const unreadCountResponse = z.object({ data: z.object({ unread: z.number().int() }) }).transform((response) => response.data.unread);

export const inboxQueryKey = ['inbox'] as const;
export const unreadCountQueryKey = [...inboxQueryKey, 'unread-count'] as const;

// Máximo de mensajes por marca en lote, el mismo que valida el backend.
export const maxMarkedMessages = 200;

export function fetchInbox(params: ListParams, signal?: AbortSignal): Promise<ListPage<InboxMessage>> {
  return api.request('GET', listPath('/me/inbox', params), { schema: inboxPage, signal });
}

export function fetchUnreadCount(signal?: AbortSignal): Promise<number> {
  return api.request('GET', '/me/inbox/unread-count', { schema: unreadCountResponse, signal });
}

export interface InboxMarks {
  read?: boolean;
  archived?: boolean;
}

export async function markInboxMessages(ids: readonly number[], marks: InboxMarks): Promise<void> {
  await api.request('PATCH', '/me/inbox', { body: { ids, ...marks } });
}
