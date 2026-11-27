import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useScreenContext } from '@/app/shell/useScreenContext';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort } from '@/shared/ui/grid/gridTypes';
import { fetchInbox, inboxQueryKey, markInboxMessages, type InboxFolder, type InboxMarks, type InboxMessage } from './inboxApi';
import { inboxMessageLink, useInboxMessageText } from './inboxMessages';

const folders: readonly InboxFolder[] = ['inbox', 'archived'];
const newestFirst: GridSort = { columnId: 'created_at', desc: true };

type MarkAction = 'read' | 'unread' | 'archive' | 'restore';

const marksOf: Record<MarkAction, InboxMarks> = {
  read: { read: true },
  unread: { read: false },
  archive: { archived: true },
  restore: { archived: false },
};

function sameIds(a: readonly InboxMessage[], b: readonly InboxMessage[]): boolean {
  return a.length === b.length && a.every((message, index) => message.id === b.at(index)?.id && message.read_at === b.at(index)?.read_at);
}

// SBWP Bandeja de entrada (E3.27): los avisos propios en dos carpetas, Entrada y Archivados.
// Los no leídos van en negrita; las marcas se aplican a la selección múltiple de la página
// visible. Doble clic o Intro marca leído y abre el objeto del aviso, si tiene.
export function InboxTransaction() {
  const { t } = useTranslation('inbox');
  const { show } = useStatusBar();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const text = useInboxMessageText();
  const [folder, setFolder] = useState<InboxFolder>('inbox');
  const [selected, setSelected] = useState<InboxMessage[]>([]);
  const baseId = useId();
  const tabRefs = useRef<Partial<Record<InboxFolder, HTMLButtonElement | null>>>({});

  useScreenContext(folder);

  const mark = useMutation({
    mutationFn: ({ ids, marks }: { ids: number[]; marks: InboxMarks }) => markInboxMessages(ids, marks),
    onSettled: () => queryClient.invalidateQueries({ queryKey: inboxQueryKey }),
  });

  const apply = (action: MarkAction, messages: readonly InboxMessage[], silent = false) => {
    mark.mutate(
      { ids: messages.map((message) => message.id), marks: marksOf[action] },
      {
        onSuccess: () => {
          if (!silent) {
            show({ type: 'S', text: t(`messages.${action}`, { count: messages.length }) });
          }
        },
        onError: (error) => {
          show(errorMessage(error, t('messages.markFailed')));
        },
      },
    );
  };

  const columns = useMemo(
    (): GridColumn<InboxMessage>[] => [
      { id: 'created_at', header: t('columns.received'), value: (row) => row.created_at, type: 'datetime', field: 'id', filter: false },
      {
        id: 'text',
        header: t('columns.message'),
        value: (row) => text(row),
        sortable: false,
        filter: false,
        cell: (row) => <span className={row.read_at === null ? 'font-semibold' : undefined}>{text(row)}</span>,
      },
      {
        id: 'status',
        header: t('columns.status'),
        value: (row) => t(row.read_at === null ? 'status.unread' : 'status.read'),
        sortable: false,
        filter: false,
      },
    ],
    [t, text],
  );

  const source = useMemo(
    () => ({
      kind: 'server' as const,
      queryKey: [...inboxQueryKey, 'list', folder],
      fetchPage: (params: Parameters<typeof fetchInbox>[0], signal?: AbortSignal) =>
        fetchInbox({ ...params, filters: { ...params.filters, folder } }, signal),
    }),
    [folder],
  );

  const none = selected.length === 0 || mark.isPending;
  const actions: ApplicationAction[] =
    folder === 'inbox'
      ? [
          { id: 'read', label: t('actions.read'), disabled: none || selected.every((row) => row.read_at !== null), onClick: () => { apply('read', selected); } },
          { id: 'unread', label: t('actions.unread'), disabled: none || selected.every((row) => row.read_at === null), onClick: () => { apply('unread', selected); } },
          { id: 'archive', label: t('actions.archive'), disabled: none, onClick: () => { apply('archive', selected); } },
        ]
      : [{ id: 'restore', label: t('actions.restore'), disabled: none, onClick: () => { apply('restore', selected); } }];

  const changeFolder = (next: InboxFolder) => {
    setFolder(next);
    setSelected([]);
  };

  // Flechas entre carpetas con activación automática, como el patrón de pestañas de la APG.
  const onTabKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;

    if (step === 0) {
      return;
    }

    event.preventDefault();
    const next = folders[(folders.indexOf(folder) + step + folders.length) % folders.length] ?? 'inbox';
    changeFolder(next);
    tabRefs.current[next]?.focus();
  };

  return (
    <div className="grid gap-3">
      <div role="tablist" aria-label={t('folders.label')} className="flex gap-1 border-b border-border">
        {folders.map((candidate) => (
          <button
            key={candidate}
            ref={(element) => {
              tabRefs.current[candidate] = element;
            }}
            type="button"
            role="tab"
            id={`${baseId}-${candidate}-tab`}
            aria-selected={folder === candidate}
            aria-controls={`${baseId}-panel`}
            tabIndex={folder === candidate ? 0 : -1}
            className={`-mb-px border border-b-0 px-3 py-1 ${folder === candidate ? 'border-border bg-surface font-medium' : 'border-transparent text-ink-muted'}`}
            onClick={() => {
              changeFolder(candidate);
            }}
            onKeyDown={onTabKeyDown}
          >
            {t(`folders.${candidate}`)}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`${baseId}-panel`} aria-labelledby={`${baseId}-${folder}-tab`} className="grid gap-3">
        <ApplicationToolbar label={t('actions.label')} actions={actions} />
        <DataGrid
          key={folder}
          label={t(`folders.${folder}`)}
          columns={columns}
          source={source}
          rowKey={(row: InboxMessage) => row.id}
          exportName={t('exportName')}
          onMessage={show}
          selection="multiple"
          onSelectionChange={(rows: InboxMessage[]) => {
            // La grilla informa la selección en cada recarga: sin cambios, no se vuelve a pintar.
            setSelected((current) => (sameIds(current, rows) ? current : rows));
          }}
          initialSort={newestFirst}
          onRowActivate={(row: InboxMessage) => {
            if (row.read_at === null) {
              apply('read', [row], true);
            }

            const link = inboxMessageLink(row);

            if (link !== undefined) {
              void navigate(link);
            }
          }}
        />
      </div>
    </div>
  );
}
