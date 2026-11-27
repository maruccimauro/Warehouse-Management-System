import { useCallback, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { PhraseConfirmDialog, type PhraseConfirmTone } from '@/shared/ui/PhraseConfirmDialog';
import type { RoleName } from './usersApi';
import { useRoleLabel } from './userValues';

// Roles críticos (E3.28, D16.4 y D16.11): asignarlos pide escribir una frase, en rojo el que
// administra el sistema y en ámbar los de control de operaciones. El backend exige la misma
// confirmación en confirmed_critical_roles (CRITICAL_ROLE_CONFIRMATION_REQUIRED).
export const criticalRoles: Readonly<Partial<Record<RoleName, PhraseConfirmTone>>> = {
  administrator: 'danger',
  operations_manager: 'warning',
  operations_lead: 'warning',
};

export function isCriticalRole(name: string): name is RoleName {
  return Object.hasOwn(criticalRoles, name);
}

// Roles críticos que la asignación suma respecto de los que el usuario ya tenía.
export function addedCriticalRoles(before: readonly string[], after: readonly string[]): RoleName[] {
  return after.filter((name): name is RoleName => isCriticalRole(name) && !before.includes(name));
}

interface PendingConfirmation {
  roles: RoleName[];
  index: number;
  resolve: (confirmed: boolean) => void;
}

// Confirma uno por uno los roles críticos sumados; resuelve false si se cancela cualquiera.
export function useCriticalRoleConfirmation(): { confirm: (roles: readonly RoleName[]) => Promise<boolean>; dialog: ReactNode } {
  const { t } = useTranslation('users');
  const roleLabel = useRoleLabel();
  const [pending, setPending] = useState<PendingConfirmation>();
  const pendingRef = useRef<PendingConfirmation>(undefined);

  const update = (next: PendingConfirmation | undefined) => {
    pendingRef.current = next;
    setPending(next);
  };

  const confirm = useCallback((roles: readonly RoleName[]) => {
    if (roles.length === 0) {
      return Promise.resolve(true);
    }

    return new Promise<boolean>((resolve) => {
      update({ roles: [...roles], index: 0, resolve });
    });
  }, []);

  const role = pending?.roles[pending.index];
  const dialog =
    role === undefined ? null : (
      <PhraseConfirmDialog
        open
        tone={criticalRoles[role] ?? 'warning'}
        title={t('criticalRole.title', { role: roleLabel(role) })}
        message={t(`criticalRole.message.${criticalRoles[role] ?? 'warning'}`, { role: roleLabel(role) })}
        phrase={t(`criticalRole.phrase.${role}` as 'criticalRole.phrase.administrator')}
        inputLabel={t('criticalRole.inputLabel')}
        confirmLabel={t('criticalRole.confirm')}
        cancelLabel={t('criticalRole.cancel')}
        onConfirm={() => {
          const current = pendingRef.current;

          if (current === undefined) {
            return;
          }

          if (current.index + 1 < current.roles.length) {
            update({ ...current, index: current.index + 1 });
          } else {
            update(undefined);
            current.resolve(true);
          }
        }}
        onCancel={() => {
          const current = pendingRef.current;
          update(undefined);
          current?.resolve(false);
        }}
      />
    );

  return { confirm, dialog };
}
