<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;

/**
 * Cierre de las sesiones abiertas de un usuario (REG-020, D2): al
 * desactivarlo y al cambiar o resetear su contraseña.
 *
 * Con el driver database borra las filas de la tabla de sesiones por user_id,
 * dentro de la transacción del cambio. Con otro driver (Redis, ADR-011) no hay
 * forma de buscar por usuario y no hace nada: la garantía de fondo la dan los
 * middlewares EnsureUserIsActive y RequirePasswordChange, que también cubren
 * una petición en curso o un login simultáneo con la desactivación.
 */
final class UserSessions
{
    public function __construct(
        private readonly Config $config,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  string|null  $exceptSessionId  sesión que se conserva: la de quien
     *                                        cambia su propia contraseña
     */
    public function invalidate(User $user, ?string $exceptSessionId = null): void
    {
        if ($this->config->get('session.driver') !== 'database') {
            return;
        }

        $table = $this->config->get('session.table', 'sessions');

        $this->db->connection($this->sessionConnection())
            ->table(is_string($table) ? $table : 'sessions')
            ->where('user_id', $user->id)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    private function sessionConnection(): ?string
    {
        $connection = $this->config->get('session.connection');

        return is_string($connection) ? $connection : null;
    }
}
