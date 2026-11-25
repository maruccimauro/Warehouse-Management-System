import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { TransactionFieldControlProps } from '@/app/shell/useTransactionForm';
import { SearchHelpField, type SearchHelpFieldProps } from '@/shared/ui/searchHelp/SearchHelpField';
import type { SearchHelp } from '@/shared/ui/searchHelp/searchHelpTypes';
import { checkListValue, checkListValues, useRoleLabel, userStatuses } from './userValues';
import { fetchUsers, normalizeUsername, roleNames, usersQueryKey, type RoleName, type User } from './usersApi';

type UserFieldProps = Omit<SearchHelpFieldProps<User>, 'searchHelp' | 'check'>;

// Campo Usuario con F4 de servidor (E2.14): por código o nombre (patrón con * en los extremos)
// o por fragmento del email. El campo acepta el código; sin tabla de verificación, porque la
// pantalla lee el usuario al continuar e informa USER_NOT_FOUND.
export function UserField(props: UserFieldProps) {
  const { t } = useTranslation('users');

  const searchHelp = useMemo(
    (): SearchHelp<User> => ({
      title: t('userHelp.title'),
      columns: [
        { id: 'username', header: t('fields.username'), value: (row) => row.username },
        { id: 'name', header: t('fields.name'), value: (row) => row.name },
        { id: 'email', header: t('fields.email'), value: (row) => row.email },
        { id: 'is_active', header: t('fields.status'), value: (row) => (row.is_active ? 'active' : 'inactive'), format: (value) => t(`statuses.${String(value) as 'active' | 'inactive'}`) },
      ],
      source: {
        kind: 'server',
        queryKey: [...usersQueryKey, 'help'],
        fetchPage: (params, signal) => fetchUsers({ ...params, sort: params.sort ?? { field: 'username', desc: false } }, signal),
        filtersOf: (criteria) => ({
          ...(criteria['username'] === undefined ? {} : { username: [{ sign: 'I', option: 'CP', low: `*${normalizeUsername(criteria['username'])}*` }] }),
          ...(criteria['name'] === undefined ? {} : { name: [{ sign: 'I', option: 'CP', low: `*${criteria['name'].trim()}*` }] }),
          ...(criteria['email'] === undefined ? {} : { email: criteria['email'].trim() }),
        }),
      },
      criteria: ['username', 'name', 'email'],
      valueOf: (row) => row.username,
      descriptionOf: (row) => row.name,
      normalize: normalizeUsername,
      invalidMessage: (username) => t('messages.notFound', { username }),
      exportName: 'usuarios',
    }),
    [t],
  );

  return <SearchHelpField {...props} check={false} searchHelp={searchHelp} />;
}

interface RoleRow {
  name: RoleName;
  label: string;
}

type RoleFieldProps = Omit<SearchHelpFieldProps<RoleRow>, 'searchHelp'>;

// Campo Rol con F4 local y tabla de verificación: los cinco roles del sistema con su nombre
// traducido. El valor es el identificador del rol (operator), que es el que guarda la base.
export function RoleField(props: RoleFieldProps) {
  const { t } = useTranslation('roles');
  const roleLabel = useRoleLabel();

  const searchHelp = useMemo((): SearchHelp<RoleRow> => {
    const rows = roleNames.map((name) => ({ name, label: roleLabel(name) }));
    // Se acepta el identificador o el nombre traducido, sin distinguir mayúsculas.
    const byText = new Map(rows.flatMap((row) => [[row.name, row.name] as const, [row.label.toLocaleLowerCase('es'), row.name] as const]));

    return {
      title: t('roleHelp.title'),
      columns: [
        { id: 'name', header: t('roleHelp.code'), value: (row) => row.name },
        { id: 'label', header: t('roleHelp.name'), value: (row) => row.label },
      ],
      source: { kind: 'local', rows },
      criteria: ['name', 'label'],
      valueOf: (row) => row.name,
      descriptionOf: (row) => row.label,
      normalize: (text) => byText.get(text.trim().toLocaleLowerCase('es')) ?? text.trim(),
      invalidMessage: (role) => t('roleHelp.invalid', { role }),
      exportName: 'roles',
    };
    // roleLabel depende solo de t.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [t]);

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}

interface StatusRow {
  value: (typeof userStatuses)[number];
  label: string;
}

type UserStatusFieldProps = Omit<SearchHelpFieldProps<StatusRow>, 'searchHelp'>;

// Estado del usuario como criterio de la lista (SUIM), con F4 local y tabla de verificación. Se
// acepta el estado (active) o su nombre (Activo).
export function UserStatusField(props: UserStatusFieldProps) {
  const { t } = useTranslation('users');

  const searchHelp = useMemo((): SearchHelp<StatusRow> => {
    const rows = userStatuses.map((value) => ({ value, label: t(`statuses.${value}`) }));
    const byText = new Map(rows.flatMap((row) => [[row.value, row.value] as const, [row.label.toLocaleLowerCase('es'), row.value] as const]));

    return {
      title: t('fields.status'),
      columns: [{ id: 'label', header: t('fields.status'), value: (row) => row.label }],
      source: { kind: 'local', rows },
      criteria: ['label'],
      valueOf: (row) => row.value,
      descriptionOf: (row) => row.label,
      normalize: (text) => byText.get(text.trim().toLocaleLowerCase('es')) ?? text.trim(),
      invalidMessage: (status) => t('messages.statusInvalid', { status }),
      exportName: 'estados',
    };
  }, [t]);

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}

export interface CheckListOption {
  value: string;
  label: string;
  description?: string | undefined;
}

// Lista de casillas como control de un campo (roles y depósitos del usuario): un grupo
// nombrado por la etiqueta del campo, una casilla por opción. En solo lectura las casillas
// quedan deshabilitadas y muestran lo asignado.
export function CheckList({ options, emptyText, ...props }: TransactionFieldControlProps & { options: readonly CheckListOption[]; emptyText: string }) {
  const selected = new Set(checkListValues(props.value));

  if (options.length === 0) {
    return (
      <p id={props.id} className="text-ink-muted">
        {emptyText}
      </p>
    );
  }

  return (
    <div
      id={props.id}
      role="group"
      aria-labelledby={props['aria-labelledby'] ?? props.labelId}
      aria-describedby={props['aria-describedby']}
      aria-invalid={props['aria-invalid']}
      className="grid max-h-56 gap-1 overflow-auto rounded border border-border p-2"
    >
      {options.map((option) => (
        <label key={option.value} className="flex items-baseline gap-2">
          <input
            type="checkbox"
            checked={selected.has(option.value)}
            disabled={props.disabled}
            onChange={(event) => {
              const next = new Set(selected);

              if (event.target.checked) {
                next.add(option.value);
              } else {
                next.delete(option.value);
              }

              props.onChange(checkListValue([...next]), true);
            }}
            onBlur={props.onBlur}
          />
          <span>{option.label}</span>
          {option.description !== undefined && <span className="text-ink-muted">{option.description}</span>}
        </label>
      ))}
    </div>
  );
}
