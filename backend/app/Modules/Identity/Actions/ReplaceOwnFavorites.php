<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Graba los favoritos como lista ordenada completa (E2.21, D6): la posición
 * es el índice en la lista. Con la fila del usuario bloqueada, dos
 * grabaciones simultáneas no chocan en la clave primaria; gana la última.
 */
final class ReplaceOwnFavorites
{
    /**
     * @param  list<string>  $codes  códigos distintos, ya validados
     */
    public function __invoke(User $user, array $codes): void
    {
        DB::transaction(function () use ($user, $codes): void {
            User::query()->lock('for no key update')->findOrFail($user->id);

            DB::table('user_favorites')->where('user_id', $user->id)->delete();

            if ($codes === []) {
                return;
            }

            DB::table('user_favorites')->insert(array_map(
                fn (string $code, int $position): array => [
                    'user_id' => $user->id,
                    'transaction_code' => $code,
                    'position' => $position,
                ],
                $codes,
                array_keys($codes),
            ));
        });
    }
}
