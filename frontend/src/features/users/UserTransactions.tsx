import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useInitialScreenFlow } from '@/app/shell/useInitialScreenFlow';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { i18n } from '@/i18n';
import { UserDetail, type UserMode } from './UserDetail';
import { UserField } from './UserFields';
import { userLinkParams } from './userLinks';
import { findUser, normalizeUsername, usernamePattern, type User } from './usersApi';

// SU01 Gestionar usuarios (E2.14, D2): pantalla inicial con el usuario y las acciones Crear,
// Modificar y Visualizar en la barra, como SU01 de SAP. Crear toma el código escrito y verifica
// que no exista; Modificar y Visualizar leen el usuario. Desde visualizar se pasa a modificar.
type ManageScreen = { kind: 'initial' } | { kind: 'detail'; mode: UserMode; user: User | undefined; username: string };

export function ManageUsersTransaction() {
  const [screen, setScreen] = useState<ManageScreen>({ kind: 'initial' });
  const [last, setLast] = useState<string>();

  if (screen.kind === 'initial') {
    return (
      <UserInitialScreen
        title="manage"
        initial={last}
        onContinue={(mode, user, username) => {
          setLast(username);
          setScreen({ kind: 'detail', mode, user, username });
        }}
      />
    );
  }

  return (
    <UserDetail
      key={`${screen.mode}-${String(screen.user?.id ?? 'new')}`}
      mode={screen.mode}
      user={screen.user}
      username={screen.username}
      onModeChange={(mode, user) => {
        setLast(user.username);
        setScreen({ kind: 'detail', mode, user, username: user.username });
      }}
      onBack={() => {
        setScreen({ kind: 'initial' });
      }}
    />
  );
}

// ZW91 Crear usuario: la ficha vacía con el código editable. Cada grabación vuelve a una ficha
// vacía para el alta siguiente; el código repetido se informa al grabar (VALUE_ALREADY_EXISTS).
export function CreateUserTransaction() {
  const [round, setRound] = useState(0);
  const next = () => {
    setRound((current) => current + 1);
  };

  return <UserDetail key={round} mode="create" user={undefined} standalone onModeChange={next} onBack={next} />;
}

// ZW92 Modificar usuario y ZW93 Visualizar usuario: pantalla inicial con el usuario y la ficha.
export function ChangeUserTransaction() {
  return <UserActivity activity="change" />;
}

export function DisplayUserTransaction() {
  return <UserActivity activity="display" />;
}

function UserActivity({ activity }: { activity: 'change' | 'display' }) {
  const flow = useInitialScreenFlow((next: User) => next.username);

  if (flow.target === undefined) {
    return (
      <UserInitialScreen
        title={activity}
        initial={flow.last}
        onContinue={(_mode, user) => {
          if (user !== undefined) {
            flow.open(user);
          }
        }}
      />
    );
  }

  return (
    <UserDetail
      mode={activity}
      user={flow.target}
      standalone
      // Grabar vuelve a la pantalla inicial con el usuario, listo para el siguiente.
      onModeChange={flow.close}
      onBack={flow.close}
    />
  );
}

interface UserInitialScreenProps {
  // manage: SU01, con las tres acciones en la barra; change y display: F8 continúa.
  title: 'manage' | 'change' | 'display';
  initial: string | undefined;
  onContinue: (mode: UserMode, user: User | undefined, username: string) => void;
}

// Pantalla inicial: el usuario, escrito o elegido con F4 por código, nombre o email. Se lee al
// continuar; un usuario inexistente se informa con USER_NOT_FOUND.
function UserInitialScreen({ title, initial, onContinue }: UserInitialScreenProps) {
  const { t } = useTranslation('users');
  const { show } = useStatusBar();
  const [searchParams, setSearchParams] = useSearchParams();

  const fields = useMemo(
    (): SelectionField[] => [{ kind: 'parameter', id: 'user', label: t('fields.user'), as: UserInput, required: true }],
    [t],
  );
  const linkedUser = searchParams.get(userLinkParams.user);
  const selection = useSelectionScreen(fields, { user: initial ?? linkedUser ?? '' });
  // Sin bloqueo de una lectura en curso: es un GET sin efectos y repetirlo abre la misma ficha.

  const run = async (mode: UserMode) => {
    const values = selection.validate(show);

    if (values === undefined) {
      return;
    }

    const username = normalizeUsername(parameterValue(values, 'user'));

    if (mode === 'create' && !usernamePattern.test(username)) {
      show({ type: 'E', text: `${t('fields.user')}: ${i18n.t('rules.username', { ns: 'form' })}`, id: 'VALIDATION_FAILED' });

      return;
    }

    try {
      const user = usernamePattern.test(username) ? await findUser(username) : undefined;

      if (mode === 'create') {
        if (user !== undefined) {
          show({ type: 'E', text: t('messages.alreadyExists', { username }), id: 'VALUE_ALREADY_EXISTS' });

          return;
        }

        onContinue('create', undefined, username);

        return;
      }

      if (user === undefined) {
        show({ type: 'E', text: t('messages.notFound', { username }), id: 'USER_NOT_FOUND' });

        return;
      }

      onContinue(mode, user, username);
    } catch (error) {
      show(errorMessage(error, t('messages.loadFailed')));
    }
  };

  // Las transacciones individuales continúan con F8; la gestión, con la acción de la barra.
  useTransactionActions({
    execute:
      title === 'manage'
        ? undefined
        : () => {
            void run(title);
          },
  });

  // Abierta desde un enlace con el usuario (la lista o un rol): lo lee sin esperar F8 y quita
  // el parámetro para que F3 vuelva a una pantalla inicial normal.
  useEffect(() => {
    if (linkedUser !== null && title !== 'manage') {
      setSearchParams({}, { replace: true });
      void run(title);
    }
    // Solo al abrir desde el enlace.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [linkedUser !== null]);

  const actions: ApplicationAction[] = [];

  if (title === 'manage') {
    for (const mode of ['create', 'change', 'display'] as const) {
      actions.push({ id: mode, label: t(`actions.${mode}`), onClick: () => void run(mode) });
    }
  }

  return (
    <div className="grid gap-3">
      <ApplicationToolbar label={t('actions.manageActions')} actions={actions} />
      <SelectionScreen selection={selection} title={t(`initial.${title}`)} onMessage={show} />
      <InfoNotice>{t(title === 'manage' ? 'initial.manageHint' : 'initial.hint')}</InfoNotice>
    </div>
  );
}

// Usuario con F4 de servidor como entrada de la pantalla inicial: sin tabla de verificación,
// porque la pantalla lee el usuario e informa si no existe (y Crear exige que no exista).
function UserInput(props: SelectionInputProps) {
  return <UserField {...props} />;
}
