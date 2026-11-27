<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Identity\Data\UserChanges;
use App\Modules\Identity\Exceptions\SelfDeactivationNotAllowed;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AdministratorContinuity;
use App\Modules\Identity\Services\CriticalRoleNotifier;
use App\Modules\Identity\Services\UserAssignments;
use App\Modules\Identity\Services\UserSessions;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Support\Facades\DB;

/**
 * Modificación de un usuario (SU01): datos, activación, roles, depósitos y
 * reseteo de contraseña, todo en una transacción (D6).
 *
 * Orden de bloqueo: si el cambio puede achicar el conjunto de administradores
 * activos, primero el bloqueo consultivo de AdministratorContinuity y después
 * la fila del usuario con FOR NO KEY UPDATE. Un cambio que no toca roles ni
 * desactiva toma solo la fila, así que nunca espera el consultivo teniendo la
 * fila: no hay ciclo de espera.
 *
 * Reglas:
 * - Nadie se desactiva a sí mismo y siempre queda un administrador activo
 *   (REG-022).
 * - Desactivar y resetear la contraseña cierran las sesiones abiertas del
 *   usuario (REG-020). El reseteo deja la contraseña como inicial: el usuario
 *   debe cambiarla antes de operar (REG-021).
 * - Un cambio de roles rige en la petición siguiente sin cerrar sesión:
 *   spatie/laravel-permission resuelve los roles del usuario en cada petición.
 *
 * Se auditan los cambios de acceso (INV-012): activación, roles, depósitos y
 * reseteo de contraseña. Nombre, email e idioma son datos maestros y no se
 * auditan. Sumar un rol crítico avisa a los Administradores (REG-025).
 */
final class UpdateUser
{
    private const EDITABLE = ['name', 'email', 'locale'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AdministratorContinuity $continuity,
        private readonly UserSessions $sessions,
        private readonly CriticalRoleNotifier $criticalRoles,
    ) {}

    /**
     * @param  string|null  $currentSessionId  sesión de quien hace el cambio, que
     *                                         nunca se cierra
     *
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(User $actor, User $user, UserChanges $changes, ?string $currentSessionId = null): User
    {
        if ($changes->isActive === false && $actor->is($user)) {
            throw new SelfDeactivationNotAllowed;
        }

        return DB::transaction(function () use ($actor, $user, $changes, $currentSessionId): User {
            if ($changes->mayRemoveAdministrator()) {
                $this->continuity->lock();
            }

            $user = User::query()->lock('for no key update')->findOrFail($user->id);
            $wasActive = $user->is_active;

            $user->fill(array_intersect_key($changes->attributes, array_flip(self::EDITABLE)));

            if ($changes->isActive !== null) {
                $user->is_active = $changes->isActive;
            }

            if ($changes->password !== null) {
                $user->password = $changes->password;
                $user->password_change_required = true;
            }

            $user->save();

            $this->applyRoles($actor, $user, $changes);
            $this->applyWarehouses($user, $changes);
            $this->recordActivation($user, $wasActive);

            if ($changes->password !== null) {
                $this->audit->record('user.password_reset', $user, null, null, null);
            }

            $deactivated = $wasActive && ! $user->is_active;

            if ($deactivated || $changes->password !== null) {
                $this->sessions->invalidate($user, $currentSessionId);
            }

            if ($changes->mayRemoveAdministrator()) {
                $this->continuity->ensureRemains();
            }

            return $user->refresh()->load(['roles', 'warehouses']);
        });
    }

    private function applyRoles(User $actor, User $user, UserChanges $changes): void
    {
        if ($changes->roles === null) {
            return;
        }

        $before = UserAssignments::currentRoleNames($user);
        $after = UserAssignments::roleNames($changes->roles);

        if ($before === $after) {
            return;
        }

        $user->syncRoles($after);
        $this->audit->record('user.roles_changed', $user, null, ['roles' => $before], ['roles' => $after]);
        $this->criticalRoles->notify($user, $before, $after, $actor);
    }

    private function applyWarehouses(User $user, UserChanges $changes): void
    {
        if ($changes->warehouseIds === null) {
            return;
        }

        $before = UserAssignments::currentWarehouseIds($user);
        $after = UserAssignments::sortedIds($changes->warehouseIds);

        if ($before === $after) {
            return;
        }

        $user->warehouses()->sync($after);
        $this->audit->record('user.warehouses_changed', $user, null, ['warehouse_ids' => $before], ['warehouse_ids' => $after]);
    }

    private function recordActivation(User $user, bool $wasActive): void
    {
        if ($user->is_active === $wasActive) {
            return;
        }

        $this->audit->record(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            null,
            ['is_active' => $wasActive],
            ['is_active' => $user->is_active],
        );
    }
}
