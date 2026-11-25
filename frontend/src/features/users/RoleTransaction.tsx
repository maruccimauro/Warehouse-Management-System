import { useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useInitialScreenFlow } from '@/app/shell/useInitialScreenFlow';
import { HelpLink } from '@/shared/help/HelpLink';
import { isHelpKey } from '@/shared/help/helpLinks';
import { InfoNotice } from '@/shared/ui/InfoNotice';
import { FormSection } from '@/shared/ui/form/FormSection';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { moduleOrder, permissionModules } from './permissionModules';
import { RoleField } from './UserFields';
import { useRoleLabel, useRoles } from './userValues';
import { UserResult } from './UserListTransaction';
import { isRoleName, type Role } from './usersApi';

// PFCG Visualizar rol (E2.14, D4): pantalla inicial con el rol y la ficha con dos pestañas,
// Permisos (agrupados por módulo, con su descripción y la ayuda de la referencia) y Usuarios
// (los que tienen el rol, con GET /users?filter[role]=). Los roles los define el sistema y no
// se editan desde la interfaz.
export function DisplayRoleTransaction() {
  const flow = useInitialScreenFlow((next: Role) => next.name);

  if (flow.target === undefined) {
    return <RoleInitialScreen initial={flow.last} onContinue={flow.open} />;
  }

  return <RoleDetail role={flow.target} onBack={flow.close} />;
}

function RoleInitialScreen({ initial, onContinue }: { initial: string | undefined; onContinue: (role: Role) => void }) {
  const { t } = useTranslation('roles');
  const { show } = useStatusBar();
  const roles = useRoles();
  const fields = useMemo(
    (): SelectionField[] => [{ kind: 'parameter', id: 'role', label: t('fields.role'), as: RoleInput, required: true }],
    [t],
  );
  const selection = useSelectionScreen(fields, { role: initial ?? '' });

  useTransactionActions({
    execute: () => {
      const values = selection.validate(show);

      if (values === undefined) {
        return;
      }

      if (roles.error !== null) {
        show(errorMessage(roles.error, t('messages.loadFailed')));

        return;
      }

      const name = parameterValue(values, 'role');
      const role = roles.data?.find((candidate) => candidate.name === name);

      // El campo ya informó el rol inexistente; mientras se leen los roles, F8 no hace nada.
      if (role !== undefined) {
        onContinue(role);
      }
    },
  });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t('initial.display')} onMessage={show} />
      <InfoNotice>{t('initial.hint')}</InfoNotice>
    </div>
  );
}

function RoleInput(props: SelectionInputProps) {
  return <RoleField {...props} />;
}

type RoleTab = 'permissions' | 'users';
const roleTabs: readonly RoleTab[] = ['permissions', 'users'];

function RoleDetail({ role, onBack }: { role: Role; onBack: () => void }) {
  const { t } = useTranslation('roles');
  const roleLabel = useRoleLabel();
  const [tab, setTab] = useState<RoleTab>('permissions');
  const baseId = useId();
  const tabRefs = useRef<Partial<Record<RoleTab, HTMLButtonElement | null>>>({});

  useTransactionActions({ back: onBack });

  // Flechas entre pestañas con activación automática, como el patrón de pestañas de la APG.
  const onTabKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;

    if (step === 0) {
      return;
    }

    event.preventDefault();
    const next = roleTabs[(roleTabs.indexOf(tab) + step + roleTabs.length) % roleTabs.length] ?? 'permissions';
    setTab(next);
    tabRefs.current[next]?.focus();
  };

  return (
    <div className="grid gap-3">
      <h2 className="font-medium">
        {roleLabel(role.name)} <span className="font-normal text-ink-muted">({role.name})</span>
      </h2>
      <div role="tablist" aria-label={t('tabs.label')} className="flex gap-1 border-b border-border">
        {roleTabs.map((candidate) => (
          <button
            key={candidate}
            ref={(element) => {
              tabRefs.current[candidate] = element;
            }}
            type="button"
            role="tab"
            id={`${baseId}-${candidate}-tab`}
            aria-selected={tab === candidate}
            aria-controls={`${baseId}-${candidate}-panel`}
            tabIndex={tab === candidate ? 0 : -1}
            className={`-mb-px border border-b-0 px-3 py-1 ${tab === candidate ? 'border-border bg-surface font-medium' : 'border-transparent text-ink-muted'}`}
            onClick={() => {
              setTab(candidate);
            }}
            onKeyDown={onTabKeyDown}
          >
            {t(`tabs.${candidate}`)}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`${baseId}-${tab}-panel`} aria-labelledby={`${baseId}-${tab}-tab`} className="grid gap-3">
        {tab === 'permissions' ? <RolePermissions role={role} /> : <RoleUsers role={role} />}
      </div>
    </div>
  );
}

function RolePermissions({ role }: { role: Role }) {
  const { t } = useTranslation('roles');
  const groups = permissionModules(role.permissions);
  // Permiso que la interfaz todavía no describe: se muestra con su nombre técnico.
  const describe = (permission: string) => (t as unknown as (key: string, options: { defaultValue: string }) => string)(`permissions.${permission}`, { defaultValue: '' });

  if (groups.length === 0) {
    return <p className="text-ink-muted">{t('detail.noPermissions')}</p>;
  }

  return (
    <>
      <p className="text-ink-muted">{t('detail.permissionCount', { count: role.permissions.length })}</p>
      {groups.map((group) => (
        <FormSection key={group.module} title={moduleTitle(t as unknown as (key: string) => string, group.module)}>
          <table className="w-full border-collapse text-left">
            <thead>
              <tr className="border-b border-border text-ink-muted">
                <th scope="col" className="w-64 py-1 pe-3 font-normal">
                  {t('fields.permission')}
                </th>
                <th scope="col" className="py-1 font-normal">
                  {t('fields.description')}
                </th>
              </tr>
            </thead>
            <tbody>
              {group.permissions.map((permission) => {
                const helpKey = `permission.${permission}`;

                return (
                  <tr key={permission} className="border-b border-border/50">
                    <td className="py-1 pe-3 font-mono">{isHelpKey(helpKey) ? <HelpLink helpKey={helpKey}>{permission}</HelpLink> : permission}</td>
                    <td className="py-1">{describe(permission)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </FormSection>
      ))}
    </>
  );
}

function moduleTitle(t: (key: string) => string, module: string): string {
  return moduleOrder.includes(module) ? t(`modules.${module}`) : module;
}

function RoleUsers({ role }: { role: Role }) {
  const { t } = useTranslation('roles');
  const filters = useMemo(() => ({ role: role.name }), [role.name]);

  if (!isRoleName(role.name)) {
    return null;
  }

  return (
    <>
      <InfoNotice>{t('detail.usersHint')}</InfoNotice>
      <UserResult filters={filters} label={t('tabs.users')} />
    </>
  );
}
