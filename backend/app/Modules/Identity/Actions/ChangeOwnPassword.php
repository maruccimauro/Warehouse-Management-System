<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserSessions;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de la propia contraseña (PUT /me/password), como el cambio de clave
 * de SAP. Levanta la obligación de cambio de una contraseña inicial o
 * reseteada (REG-021) y cierra las demás sesiones del usuario, que se abrieron
 * con la contraseña anterior; la sesión actual se conserva (REG-020).
 */
final class ChangeOwnPassword
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UserSessions $sessions,
    ) {}

    public function __invoke(User $user, string $password, ?string $currentSessionId): User
    {
        return DB::transaction(function () use ($user, $password, $currentSessionId): User {
            $user = User::query()->lock('for no key update')->findOrFail($user->id);

            $user->password = $password;
            $user->password_change_required = false;
            $user->save();

            $this->sessions->invalidate($user, $currentSessionId);
            $this->audit->record('user.password_changed', $user, null, null, null);

            return $user;
        });
    }
}
