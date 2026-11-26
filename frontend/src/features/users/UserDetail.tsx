import { useQueryClient } from '@tanstack/react-query';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import type { TransactionFieldControlProps } from '@/app/shell/useTransactionForm';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { useVisibleWarehouses } from '@/features/warehouses/warehouseQueries';
import { HelpLink } from '@/shared/help/HelpLink';
import { ApplicationToolbar, type ApplicationAction } from '@/shared/ui/ApplicationToolbar';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { passwordMismatchMessage, passwordRule, requiredEmail, requiredText, usernameRule } from '@/shared/ui/form/fieldRules';
import { CheckList } from './UserFields';
import { checkListValue, checkListValues, useRoleLabel } from './userValues';
import {
  createUser,
  emailMaxLength,
  normalizeUsername,
  passwordMaxLength,
  passwordMinLength,
  roleNames,
  userNameMaxLength,
  usersQueryKey,
  updateUser,
  type User,
} from './usersApi';

export type UserMode = 'create' | 'change' | 'display';

// Valores de la ficha. Roles y depósitos van como texto (identificadores separados por coma)
// porque los bloques de transacción trabajan con texto; password es la contraseña inicial que
// se envía con la ficha, vacía si no se asigna.
function userFormSchema(mode: UserMode) {
  return z
    .object({
      username: z.string(),
      name: requiredText(userNameMaxLength),
      email: requiredEmail(emailMaxLength),
      status: z.enum(['active', 'inactive']),
      passwordStatus: z.string(),
      password: z.string(),
      passwordRepeat: z.string(),
      roles: z.string(),
      warehouses: z.string(),
    })
    .superRefine((values, context) => {
      // El código se valida solo en el alta. La contraseña es obligatoria en el alta; al
      // modificar es opcional (Restablecer contraseña) y se valida si se escribe alguna de las dos.
      if (mode === 'display') {
        return;
      }

      const resetsPassword = mode === 'create' || values.password !== '' || values.passwordRepeat !== '';

      if (!resetsPassword) {
        return;
      }

      const rules =
        mode === 'create'
          ? ([
              ['username', usernameRule(), values.username],
              ['password', passwordRule(passwordMinLength, passwordMaxLength), values.password],
            ] as const)
          : ([['password', passwordRule(passwordMinLength, passwordMaxLength), values.password]] as const);

      for (const [path, rule, value] of rules) {
        const result = rule.safeParse(value);

        if (!result.success) {
          context.addIssue({ code: 'custom', path: [path], message: result.error.issues[0]?.message ?? '' });
        }
      }

      if (values.passwordRepeat !== values.password) {
        context.addIssue({ code: 'custom', path: ['passwordRepeat'], message: passwordMismatchMessage() });
      }
    });
}

interface UserDetailProps {
  mode: UserMode;
  user: User | undefined;
  // Código ya verificado en la pantalla inicial de la gestión: el alta lo toma sin editarlo.
  username?: string | undefined;
  // Transacción individual (crear, modificar o visualizar): sin el paso de visualizar a
  // modificar, que es de la gestión (SU01).
  standalone?: boolean;
  onModeChange: (mode: 'display' | 'change', user: User) => void;
  onBack: () => void;
}

// Ficha del usuario (SU01, ZW91 a ZW93): datos, estado, roles, depósitos y contraseña inicial,
// grabados en una sola petición (DM-23). Al modificar, la contraseña se restablece en su propia
// sección y se graba con la ficha (D16.1 de E3): cierra las sesiones del usuario (REG-020) y
// el usuario debe cambiarla en su próximo ingreso (REG-021). Los mensajes del servidor (LAST_ADMINISTRATOR,
// SELF_DEACTIVATION_NOT_ALLOWED, FORBIDDEN) llegan a la barra de estado con su código.
export function UserDetail({ mode, user, username, standalone = false, onModeChange, onBack }: UserDetailProps) {
  const { t } = useTranslation('users');
  const queryClient = useQueryClient();

  const schema = useMemo(() => userFormSchema(mode), [mode]);
  const defaultValues = useMemo(
    () => ({
      username: user?.username ?? username ?? '',
      name: user?.name ?? '',
      email: user?.email ?? '',
      status: user?.is_active === false ? ('inactive' as const) : ('active' as const),
      passwordStatus: t(user?.password_change_required === true ? 'passwordStatuses.initial' : 'passwordStatuses.current'),
      password: '',
      passwordRepeat: '',
      roles: checkListValue(user?.roles ?? []),
      warehouses: checkListValue((user?.warehouses ?? []).map((warehouse) => String(warehouse.id))),
    }),
    [user, username, t],
  );

  const { Form, Section, Field, values } = useTransactionForm({
    schema,
    defaultValues,
    namespace: 'users',
    readOnly: mode === 'display',
    savedMessage: t('messages.saved', { username: user?.username ?? '' }),
    saveFailedMessage: t('messages.saveFailed'),
    serverFields: { is_active: 'status', warehouse_ids: 'warehouses' },
    // Crear como transacción individual no tiene pantalla inicial: F3 sale de la transacción.
    onBack: mode === 'create' && standalone ? undefined : onBack,
    onSave: async (form) => {
      const roles = checkListValues(form.roles);
      const warehouseIds = checkListValues(form.warehouses).map(Number);
      const saved =
        mode === 'create'
          ? await createUser({
              username: normalizeUsername(form.username),
              name: form.name,
              email: form.email,
              password: form.password,
              roles,
              warehouse_ids: warehouseIds,
            })
          : await updateUser(user?.id ?? 0, {
              name: form.name,
              email: form.email,
              is_active: form.status === 'active',
              roles,
              warehouse_ids: warehouseIds,
              ...(form.password === '' ? {} : { password: form.password }),
            });

      // La lista, la ayuda F4 y la pestaña Usuarios de los roles.
      void queryClient.invalidateQueries({ queryKey: usersQueryKey });
      onModeChange('display', saved);

      return t(mode === 'create' ? 'messages.created' : 'messages.saved', { username: saved.username });
    },
  });

  const actions: ApplicationAction[] = [];

  if (mode === 'display' && !standalone && user !== undefined) {
    actions.push({
      id: 'change',
      label: t('actions.change'),
      primary: true,
      onClick: () => {
        onModeChange('change', user);
      },
    });
  }

  const statusOptions = [
    { value: 'active', label: t('statuses.active') },
    { value: 'inactive', label: t('statuses.inactive') },
  ];

  return (
    <div className="grid gap-3">
      {mode === 'create' && <h2 className="font-medium">{t('detail.createTitle')}</h2>}
      <ApplicationToolbar label={t('detail.actions')} actions={actions} />
      <Form>
        <Section name="general">
          <Field
            name="username"
            required
            readOnly={mode !== 'create' || username !== undefined}
            className="w-40 uppercase"
            description={mode === 'create' ? t('detail.usernameHint') : undefined}
          />
          <Field name="name" required />
          <Field name="email" type="email" required />
          {mode !== 'create' && <Field name="status" options={statusOptions} />}
        </Section>
        <Section name="logon">
          {mode === 'create' ? (
            <>
              <Field name="password" type="password" required autoComplete="new-password" className="w-64" description={t('detail.passwordHint')} />
              <Field name="passwordRepeat" type="password" required autoComplete="new-password" className="w-64" />
            </>
          ) : (
            <Field name="passwordStatus" readOnly />
          )}
        </Section>
        <Section name="authorizations">
          <Field name="roles" as={RolesControl} />
          <Field name="warehouses" as={WarehousesControl} />
        </Section>
        {mode === 'change' && (
          <Section name="resetPassword">
            <Field name="password" type="password" autoComplete="new-password" className="w-64" description={t('detail.resetPasswordHint')} />
            <Field name="passwordRepeat" type="password" autoComplete="new-password" className="w-64" />
          </Section>
        )}
      </Form>
      {user !== undefined && (
        <p className="text-ink-muted">
          {t('fields.status')}: <HelpLink helpKey={`state.user.${values.status}`}>{t(`statuses.${values.status}`)}</HelpLink>
        </p>
      )}
      {values.password !== '' && mode === 'change' && <InfoNotice>{t('detail.pendingPassword')}</InfoNotice>}
      {mode !== 'display' && <InfoNotice>{t('detail.saveHint')}</InfoNotice>}
    </div>
  );
}

// Controles de los campos Roles y Depósitos. Se declaran fuera de la ficha para que la
// identidad del componente no cambie entre renders (los bloques de transacción montan `as`).
function RolesControl(props: TransactionFieldControlProps) {
  const roleLabel = useRoleLabel();
  const options = roleNames.map((name) => ({ value: name, label: roleLabel(name) }));

  return <CheckList {...props} options={options} emptyText="" />;
}

function WarehousesControl(props: TransactionFieldControlProps) {
  const { t } = useTranslation('users');
  const { data } = useVisibleWarehouses();
  const options = (data ?? []).map((warehouse) => ({ value: String(warehouse.id), label: warehouse.code, description: warehouse.name }));

  return <CheckList {...props} options={options} emptyText={t('detail.noWarehouses')} />;
}
