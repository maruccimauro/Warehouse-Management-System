import { useEffect, useId, useRef, useState, type KeyboardEvent, type MouseEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';
import { findTransaction, menuTree, type MenuNode } from '@/app/transactions';
import { useUserPreferences } from '@/features/preferences/userPreferences';
import { ContextMenu } from '@/shared/ui/ContextMenu';
import { keepsNativeContextMenu, type ContextMenuItem } from '@/shared/ui/contextMenuItems';
import { Icon } from '@/shared/ui/icons';
import { useContextMenu } from '@/shared/ui/useContextMenu';
import { openInNewWindow, transactionPath } from './command';
import { useFavoriteToggle } from './useFavoriteToggle';

const favoritesId = 'favorites';
const menuRootKey = 'menu';
const itemClass =
  'flex min-h-6 cursor-pointer items-center gap-1.5 rounded-sm px-1 py-0.5 hover:bg-hover aria-[current=page]:bg-selected aria-[current=page]:font-semibold pointer-coarse:min-h-10';

function allFolderIds(nodes: readonly MenuNode[]): string[] {
  return nodes.flatMap((node) => (node.kind === 'folder' ? [node.id, ...allFolderIds(node.children)] : []));
}

function visibleItems(tree: HTMLElement): HTMLElement[] {
  return Array.from(tree.querySelectorAll<HTMLElement>('[role="treeitem"]'));
}

function parentItem(item: HTMLElement): HTMLElement | null {
  return item.parentElement?.closest<HTMLElement>('[role="treeitem"]') ?? null;
}

// Sobre qué se hizo clic secundario: una transacción, una carpeta o el fondo del árbol.
type TreeMenuTarget = { kind: 'transaction'; code: string } | { kind: 'folder'; id: string } | { kind: 'tree' };

const allFolders = [favoritesId, ...allFolderIds(menuTree)];

interface TransactionTreeProps {
  currentCode: string | undefined;
}

// Menú de acceso al sistema como árbol (patrón Tree View de WAI-ARIA): una sola parada de
// tabulación, flechas arriba y abajo entre ítems visibles, derecha expande o entra, izquierda
// contrae o sube, Inicio y Fin. Los grupos contraídos no se renderizan, así el orden del DOM
// es el orden visible.
export function TransactionTree({ currentCode }: TransactionTreeProps) {
  const { t } = useTranslation(['shell', 'transactions']);
  const { preferences, setShowTechnicalNames } = useUserPreferences();
  const toggleFavorite = useFavoriteToggle();
  const navigate = useNavigate();
  const treeMenu = useContextMenu<TreeMenuTarget>();
  const [expanded, setExpanded] = useState<ReadonlySet<string>>(() => new Set(allFolders));
  const [activeKey, setActiveKey] = useState<string>(favoritesId);
  const treeRef = useRef<HTMLUListElement>(null);
  const checkboxId = useId();

  const toggle = (id: string, open?: boolean) => {
    setExpanded((current) => {
      const next = new Set(current);
      const shouldOpen = open ?? !next.has(id);

      if (shouldOpen) {
        next.add(id);
      } else {
        next.delete(id);
      }

      return next;
    });
  };

  const focusItem = (item: HTMLElement | null | undefined) => {
    if (item?.dataset['key'] !== undefined) {
      setActiveKey(item.dataset['key']);
      item.focus();
    }
  };

  const onKeyDown = (event: KeyboardEvent<HTMLUListElement>) => {
    const tree = treeRef.current;
    const item = event.target instanceof HTMLElement ? event.target.closest<HTMLElement>('[role="treeitem"]') : null;

    if (tree === null || item === null) {
      return;
    }

    const items = visibleItems(tree);
    const index = items.indexOf(item);
    const folderId = item.dataset['folder'];
    const isOpen = item.getAttribute('aria-expanded') === 'true';
    let handled = true;

    switch (event.key) {
      case 'ArrowDown':
        focusItem(items[index + 1]);
        break;
      case 'ArrowUp':
        focusItem(items[index - 1]);
        break;
      case 'Home':
        focusItem(items[0]);
        break;
      case 'End':
        focusItem(items.at(-1));
        break;
      case 'ArrowRight':
        if (folderId !== undefined && !isOpen) {
          toggle(folderId, true);
        } else if (folderId !== undefined) {
          focusItem(items[index + 1]);
        }
        break;
      case 'ArrowLeft':
        if (folderId !== undefined && isOpen) {
          toggle(folderId, false);
        } else {
          focusItem(parentItem(item));
        }
        break;
      case 'Enter':
      case ' ':
        // Las hojas son enlaces: Enter navega de forma nativa.
        if (folderId !== undefined) {
          toggle(folderId);
        } else if (event.key === 'Enter') {
          handled = false;
        }
        break;
      default:
        handled = false;
    }

    if (handled) {
      event.preventDefault();
      event.stopPropagation();
    }
  };

  const closeTreeMenu = treeMenu.close;

  useEffect(() => {
    closeTreeMenu();
  }, [currentCode, closeTreeMenu]);

  // Clic secundario sobre un ítem: lo selecciona (como el explorador de archivos) y ofrece
  // sus acciones. Todas existen también en otro lugar (Enter, /o en el campo de comando,
  // botón Favorito, flechas, casilla de códigos), por eso no hace falta un botón visible.
  const onContextMenu = (event: MouseEvent<HTMLUListElement>) => {
    if (keepsNativeContextMenu(event)) {
      return;
    }

    const item = event.target instanceof Element ? event.target.closest<HTMLElement>('[role="treeitem"]') : null;
    const code = item?.dataset['code'];
    const folderId = item?.dataset['folder'];

    if (item === null || (code === undefined && folderId === undefined)) {
      treeMenu.open(event, { kind: 'tree' });

      return;
    }

    focusItem(item);
    treeMenu.open(event, code === undefined ? { kind: 'folder', id: folderId ?? '' } : { kind: 'transaction', code }, item);
  };

  const treeMenuItems = (target: TreeMenuTarget): ContextMenuItem[] => {
    const showCodes: ContextMenuItem = {
      kind: 'checkbox',
      id: 'show-codes',
      label: t('tree.showCodes'),
      checked: preferences.showTechnicalNames,
      onChange: setShowTechnicalNames,
    };
    const expandAll: ContextMenuItem[] = [
      {
        kind: 'action',
        id: 'expand-all',
        label: t('contextMenu.expandAll'),
        onSelect: () => {
          setExpanded(new Set(allFolders));
        },
      },
      {
        kind: 'action',
        id: 'collapse-all',
        label: t('contextMenu.collapseAll'),
        onSelect: () => {
          setExpanded(new Set());
        },
      },
      { kind: 'separator', id: 'separator-view' },
      showCodes,
    ];

    if (target.kind === 'tree') {
      return expandAll;
    }

    if (target.kind === 'folder') {
      const isOpen = expanded.has(target.id);

      return [
        {
          kind: 'action',
          id: 'toggle',
          label: t(isOpen ? 'contextMenu.collapse' : 'contextMenu.expand'),
          onSelect: () => {
            toggle(target.id, !isOpen);
          },
        },
        { kind: 'separator', id: 'separator-all' },
        ...expandAll,
      ];
    }

    const path = transactionPath(target.code);
    const isFavorite = preferences.favorites.includes(target.code);

    return [
      {
        kind: 'action',
        id: 'execute',
        label: t('contextMenu.execute'),
        icon: 'execute',
        onSelect: () => {
          void navigate(path);
        },
      },
      {
        kind: 'action',
        id: 'new-window',
        label: t('contextMenu.openInNewWindow'),
        onSelect: () => {
          openInNewWindow(path);
        },
      },
      { kind: 'separator', id: 'separator-favorite' },
      {
        kind: 'action',
        id: 'favorite',
        label: t(isFavorite ? 'contextMenu.removeFavorite' : 'contextMenu.addFavorite'),
        icon: 'star',
        onSelect: () => {
          toggleFavorite(target.code);
        },
      },
      { kind: 'separator', id: 'separator-view' },
      showCodes,
    ];
  };

  const label = (code: string, title: string) => (preferences.showTechnicalNames ? `${code} ${title}` : title);

  const renderTransaction = (code: string, key: string) => {
    const transaction = findTransaction(code);

    if (transaction === undefined) {
      return null;
    }

    const title = t(transaction.titleKey, { ns: 'transactions' });

    return (
      <li key={key} role="none">
        <Link
          role="treeitem"
          to={transactionPath(code)}
          data-key={key}
          data-code={code}
          tabIndex={activeKey === key ? 0 : -1}
          aria-current={currentCode === code ? 'page' : undefined}
          className={`${itemClass} text-ink no-underline`}
          onFocus={() => {
            setActiveKey(key);
          }}
        >
          <span className="text-accent">
            <Icon name="transaction" />
          </span>
          <span>{label(code, title)}</span>
        </Link>
      </li>
    );
  };

  const renderFolder = (id: string, text: string, icon: 'folder' | 'star', children: () => ReactNode, empty: boolean) => {
    const isOpen = expanded.has(id);

    return (
      <li
        key={id}
        role="treeitem"
        data-key={id}
        data-folder={empty ? undefined : id}
        aria-expanded={empty ? undefined : isOpen}
        tabIndex={activeKey === id ? 0 : -1}
        className="outline-none"
        onFocus={(event) => {
          if (event.target === event.currentTarget) {
            setActiveKey(id);
          }
        }}
      >
        <span
          className={itemClass}
          onClick={() => {
            if (!empty) {
              toggle(id);
            }
          }}
        >
          <span className={`text-ink-muted transition-transform ${isOpen && !empty ? 'rotate-90' : ''} ${empty ? 'invisible' : ''}`}>
            <Icon name="chevron" className="size-3" />
          </span>
          <span className={icon === 'star' ? 'text-warning' : 'text-ink-muted'}>
            <Icon name={icon} />
          </span>
          <span>{text}</span>
        </span>
        {!empty && isOpen && (
          <ul role="group" className="ms-4">
            {children()}
          </ul>
        )}
      </li>
    );
  };

  const renderNode = (node: MenuNode, path: string): ReactNode =>
    node.kind === 'folder'
      ? renderFolder(
          node.id,
          t(node.labelKey, { ns: 'transactions' }),
          'folder',
          () => node.children.map((child) => renderNode(child, `${path}/${node.id}`)),
          node.children.length === 0,
        )
      : renderTransaction(node.code, `${path}/${node.code}`);

  const favorites = preferences.favorites.filter((code) => findTransaction(code) !== undefined);

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between gap-2 border-b border-border px-2 py-1.5">
        <h2 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">{t('tree.title')}</h2>
      </div>
      <ul
        ref={treeRef}
        role="tree"
        aria-label={t('tree.label')}
        className="flex-1 overflow-auto p-1.5 [&_[role=treeitem]:focus-visible]:outline-2 [&_[role=treeitem]:focus-visible]:outline-focus"
        onKeyDown={onKeyDown}
        onContextMenu={onContextMenu}
      >
        {renderFolder(
          favoritesId,
          favorites.length === 0 ? `${t('tree.favorites')} (${t('tree.noFavorites')})` : t('tree.favorites'),
          'star',
          () => favorites.map((code) => renderTransaction(code, `${favoritesId}/${code}`)),
          favorites.length === 0,
        )}
        {menuTree.map((node) => renderNode(node, menuRootKey))}
      </ul>
      <ContextMenu
        label={t('contextMenu.treeLabel')}
        state={treeMenu.state}
        items={treeMenu.state === null ? [] : treeMenuItems(treeMenu.state.target)}
        onClose={treeMenu.close}
      />
      <div className="flex items-center gap-2 border-t border-border px-2 py-1.5 text-xs">
        <input
          id={checkboxId}
          type="checkbox"
          className="size-4 accent-accent"
          checked={preferences.showTechnicalNames}
          onChange={(event) => {
            setShowTechnicalNames(event.target.checked);
          }}
        />
        <label htmlFor={checkboxId}>{t('tree.showCodes')}</label>
      </div>
    </div>
  );
}
