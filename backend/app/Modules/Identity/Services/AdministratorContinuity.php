<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Exceptions\LastAdministrator;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Garantiza que siempre quede al menos un administrador activo (REG-022, D3).
 *
 * El conjunto de administradores activos no es una fila que se pueda bloquear:
 * dos administradores que se quitan el rol mutuamente verían "quedan dos" y
 * confirmarían los dos. Por eso toda operación que puede achicar ese conjunto
 * (cambiar roles, desactivar) toma primero el bloqueo consultivo de
 * transacción 209001 (registrado en ADR-003) y, después de aplicar el cambio,
 * cuenta lo que queda. Con READ COMMITTED, la cuenta posterior al bloqueo ve
 * lo que confirmó la transacción anterior.
 */
final class AdministratorContinuity
{
    public const ADVISORY_LOCK_KEY = 209001;

    /**
     * Debe llamarse dentro de la transacción, antes de bloquear la fila del
     * usuario y de aplicar el cambio.
     */
    public function lock(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [self::ADVISORY_LOCK_KEY]);
    }

    /**
     * Debe llamarse dentro de la transacción, después de aplicar el cambio: si
     * no queda ningún administrador activo, la excepción la revierte.
     */
    public function ensureRemains(): void
    {
        $remaining = User::query()
            ->where('is_active', true)
            ->role(Role::Administrator->value)
            ->exists();

        if (! $remaining) {
            throw new LastAdministrator;
        }
    }
}
