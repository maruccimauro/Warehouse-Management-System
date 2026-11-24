import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Outlet, useMatch, useNavigate } from 'react-router';
import { useLogout } from '@/features/auth/session';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { useUserPreferences } from '@/features/preferences/userPreferences';
import { UserPreferencesProvider } from '@/features/preferences/UserPreferencesProvider';
import { ContextMenu } from '@/shared/ui/ContextMenu';
import { keepsNativeContextMenu, type ContextMenuItem } from '@/shared/ui/contextMenuItems';
import { Icon } from '@/shared/ui/icons';
import { useContextMenu } from '@/shared/ui/useContextMenu';
import { CommandField } from './CommandField';
import { useShellActions } from './shellActions';
import { shellShortcuts } from './shortcuts';
import { ShellActionsProvider } from './ShellActionsProvider';
import { StandardToolbar } from './StandardToolbar';
import { StatusBar } from './StatusBar';
import { errorMessage, useStatusBar } from './statusMessages';
import { StatusBarProvider } from './StatusBarProvider';
import type { TransactionOutletContext } from './TransactionScreen';
import { TransactionTree } from './TransactionTree';
import { useUnsavedChangesContext } from './unsavedChanges';
import { UnsavedChangesProvider } from './UnsavedChangesProvider';
import { useFavoriteToggle } from './useFavoriteToggle';
import { useShellKeyboard } from './useShellKeyboard';
import { useTransactionAccess } from './useTransactionAccess';

const headingId = 'main-heading';

type StandardCommand = keyof typeof shellShortcuts;

function UserMenu() {
  const { t } = useTranslation(['shell', 'errors']);
  const user = useAuthenticatedUser();
  const logoutMutation = useLogout();
  const { show } = useStatusBar();
  const { confirmDiscard } = useUnsavedChangesContext();

  return (
    <div className="flex items-center gap-3">
      <p className="text-shell-muted max-sm:hidden">
        <span className="visually-hidden">{t('user.label')}: </span>
        {user.name}
      </p>
      <button
        type="button"
        className="min-h-7 rounded-sm px-2 text-shell-fg hover:bg-shell-raised disabled:opacity-60 pointer-coarse:min-h-11"
        disabled={logoutMutation.isPending}
        onClick={() => {
          // Como el cierre de sesión de SAP, con datos sin grabar pide confirmación.
          confirmDiscard(() => {
            logoutMutation.mutate(undefined, {
              onError: (error) => {
                show(errorMessage(error, t('codes.UNKNOWN', { ns: 'errors' })));
              },
            });
          });
        }}
      >
        {logoutMutation.isPending ? t('user.loggingOut') : t('user.logout')}
      </button>
    </div>
  );
}

function ShellLayout() {
  const { t, i18n } = useTranslation(['shell', 'transactions', 'common']);
  const user = useAuthenticatedUser();
  const navigate = useNavigate();
  const { available, run } = useShellActions();
  const { preferences } = useUserPreferences();
  const { allowed } = useTransactionAccess();
  const toggleFavorite = useFavoriteToggle();
  const workAreaMenu = useContextMenu<true>();
  const [menuOpen, setMenuOpen] = useState(false);
  const navId = useId();
  const mainRef = useRef<HTMLElement>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);

  const match = useMatch('/t/:code');
  // Una ruta sin autorización la rechaza TransactionScreen; acá no se muestra su título.
  const transaction = match?.params.code === undefined ? undefined : allowed(match.params.code);
  const code = transaction?.code;
  const title = transaction === undefined ? t('menuTitle') : t(transaction.titleKey, { ns: 'transactions' });
  const inTransaction = transaction !== undefined;

  const back = () => {
    if (!run('back')) {
      void navigate('/');
    }
  };

  const exit = () => {
    void navigate('/');
  };

  useShellKeyboard({ inTransaction, back, exit, run });

  // Un menú abierto pierde sentido al cambiar de pantalla (por ejemplo, con F3).
  const closeWorkAreaMenu = workAreaMenu.close;

  useEffect(() => {
    closeWorkAreaMenu();
  }, [code, closeWorkAreaMenu]);

  // Al cambiar de transacción el foco va al primer campo editable, como el cursor de SAP
  // GUI, o al título si la pantalla no tiene campos. En la carga inicial no se mueve. Con
  // la carga diferida, el foco espera a que TransactionScreen avise que el contenido está.
  const [readyCode, setReadyCode] = useState<string | undefined>(undefined);
  const focusedFor = useRef(code);
  const onTransactionReady = useCallback((ready: string | undefined) => {
    setReadyCode(ready);
  }, []);
  const outletContext = useMemo<TransactionOutletContext>(() => ({ onTransactionReady }), [onTransactionReady]);

  useEffect(() => {
    if (focusedFor.current === code || (code !== undefined && readyCode !== code)) {
      return;
    }

    focusedFor.current = code;
    const firstField = mainRef.current?.querySelector<HTMLElement>(
      'input:not([disabled]), select:not([disabled]), textarea:not([disabled])',
    );

    (firstField ?? headingRef.current)?.focus();
  }, [code, readyCode]);

  const defaultWarehouse = user.warehouses.find((warehouse) => warehouse.code === preferences.parameters.LGN)?.code;
  const isFavorite = code !== undefined && preferences.favorites.includes(code);

  // Clic secundario en el área de trabajo: las funciones estándar de la pantalla con sus
  // teclas, como el menú contextual de SAP GUI. Mismas acciones y disponibilidad que la barra.
  const standardItem = (command: StandardCommand, enabled: boolean, onSelect: () => void): ContextMenuItem => ({
    kind: 'action',
    id: command,
    label: t(`toolbar.${command}`),
    icon: command,
    shortcut: shellShortcuts[command],
    disabled: !enabled,
    onSelect,
  });

  const workAreaItems = (): ContextMenuItem[] => {
    const items: ContextMenuItem[] = [
      standardItem('save', available.save, () => run('save')),
      { kind: 'separator', id: 'separator-navigation' },
      standardItem('back', inTransaction, back),
      standardItem('exit', inTransaction, exit),
      { kind: 'separator', id: 'separator-execute' },
      standardItem('execute', available.execute, () => run('execute')),
    ];

    if (code !== undefined) {
      items.push({ kind: 'separator', id: 'separator-favorite' }, {
        kind: 'action',
        id: 'favorite',
        label: t(isFavorite ? 'contextMenu.removeFavorite' : 'contextMenu.addFavorite'),
        icon: 'star',
        onSelect: () => {
          toggleFavorite(code);
        },
      });
    }

    return items;
  };

  return (
    <div className="grid h-dvh grid-rows-[auto_1fr_auto] overflow-hidden">
      <title>{t('documentTitle', { title: code === undefined ? title : `${code} ${title}` })}</title>

      <a
        href={`#${headingId}`}
        className="visually-hidden fixed start-2 top-2 z-50 rounded-sm bg-surface px-3 py-2 text-accent shadow"
      >
        {t('skipToContent')}
      </a>

      <header>
        <div className="flex h-10 items-center gap-3 bg-shell px-3 text-shell-fg">
          <button
            type="button"
            className="inline-flex size-8 items-center justify-center rounded-sm hover:bg-shell-raised md:hidden"
            aria-label={t('tree.toggle')}
            aria-expanded={menuOpen}
            aria-controls={navId}
            onClick={() => {
              setMenuOpen((open) => !open);
            }}
          >
            <Icon name="menu" />
          </button>
          <span className="font-semibold tracking-wide">{t('brand')}</span>
          <span className="text-shell-muted max-sm:hidden">{t('app.fullName', { ns: 'common' })}</span>
          <div className="ms-auto">
            <UserMenu />
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2 border-b border-border bg-toolbar px-2 py-1">
          <CommandField />
          <span aria-hidden="true" className="h-5 border-s border-border-strong/40" />
          <StandardToolbar
            inTransaction={inTransaction}
            canSave={available.save}
            canExecute={available.execute}
            isFavorite={isFavorite}
            onSave={() => {
              run('save');
            }}
            onBack={back}
            onExit={exit}
            onExecute={() => {
              run('execute');
            }}
            onToggleFavorite={() => {
              if (code !== undefined) {
                toggleFavorite(code);
              }
            }}
          />
        </div>
      </header>

      <div className="grid min-h-0 grid-rows-[auto_1fr] md:grid-cols-[17rem_1fr] md:grid-rows-1">
        <nav
          id={navId}
          aria-label={t('tree.title')}
          className={`${menuOpen ? '' : 'max-md:hidden'} min-h-0 border-border bg-surface max-md:max-h-[50dvh] max-md:border-b md:border-e`}
          onClick={(event) => {
            // En pantallas angostas el menú se cierra al elegir una transacción.
            if (event.target instanceof Element && event.target.closest('a') !== null) {
              setMenuOpen(false);
            }
          }}
        >
          <TransactionTree currentCode={code} />
        </nav>

        <main
          ref={mainRef}
          className="min-h-0 overflow-auto"
          onContextMenu={(event) => {
            if (!keepsNativeContextMenu(event, { allowLinks: false })) {
              workAreaMenu.open(event, true);
            }
          }}
        >
          <div className="border-b border-border bg-surface px-4 py-2">
            <h1 id={headingId} ref={headingRef} tabIndex={-1} className="text-base font-semibold">
              {title}
            </h1>
          </div>
          <div className="p-4">
            <Outlet context={outletContext} />
          </div>
          <ContextMenu
            label={t('contextMenu.workAreaLabel')}
            state={workAreaMenu.state}
            items={workAreaMenu.state === null ? [] : workAreaItems()}
            onClose={workAreaMenu.close}
          />
        </main>
      </div>

      <StatusBar transactionCode={code} defaultWarehouse={defaultWarehouse} language={i18n.resolvedLanguage ?? i18n.language} />
    </div>
  );
}

// Armazón inspirado en SAP GUI (ADR-016): campo de comando y barra de herramientas
// estándar, árbol de transacciones, título de la pantalla y barra de estado.
// La barra de estado envuelve a las preferencias: una grabación de favoritos o de mostrar
// códigos que el servidor rechaza se informa ahí.
export function AppShell() {
  const user = useAuthenticatedUser();

  return (
    <StatusBarProvider>
      <UserPreferencesProvider key={user.id}>
        <ShellActionsProvider>
          <UnsavedChangesProvider>
            <ShellLayout />
          </UnsavedChangesProvider>
        </ShellActionsProvider>
      </UserPreferencesProvider>
    </StatusBarProvider>
  );
}
