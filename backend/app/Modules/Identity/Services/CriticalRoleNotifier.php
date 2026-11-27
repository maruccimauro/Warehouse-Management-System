<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inbox\Contracts\Inbox;

/**
 * Aviso de rol crítico (REG-025, D16.10): cada rol crítico que un usuario
 * suma deja un aviso en la bandeja de todos los Administradores activos,
 * incluido quien lo asignó, para que quede constancia. Corre en la
 * transacción de la asignación y después de sincronizar los roles.
 */
final class CriticalRoleNotifier
{
    public const MESSAGE_TYPE = 'user.critical_role_assigned';

    public function __construct(private readonly Inbox $inbox) {}

    /**
     * @param  list<string>  $before  roles anteriores del usuario
     * @param  list<string>  $after  roles nuevos del usuario
     */
    public function notify(User $user, array $before, array $after, ?User $actor): void
    {
        $added = array_values(array_filter(
            Role::critical(),
            fn (Role $role): bool => in_array($role->value, $after, true) && ! in_array($role->value, $before, true),
        ));

        if ($added === []) {
            return;
        }

        $administrators = array_values(array_map(
            fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            User::query()->where('is_active', true)->role(Role::Administrator->value)->pluck('id')->all(),
        ));

        foreach ($added as $role) {
            $this->inbox->send($administrators, self::MESSAGE_TYPE, [
                'username' => $user->username,
                'name' => $user->name,
                'role' => $role->value,
                'assigned_by' => $actor?->username,
            ], $user);
        }
    }
}
