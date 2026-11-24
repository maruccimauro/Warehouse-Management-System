<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\UserParameter;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Graba los parámetros de usuario de SU3 como reemplazo completo (E2.21, D6):
 * un parámetro sin valor se borra. Bloquea la fila del usuario para que dos
 * grabaciones simultáneas (por ejemplo, desde dos pestañas) se apliquen una
 * después de la otra; gana la última.
 */
final class ReplaceOwnParameters
{
    /**
     * @param  array<value-of<UserParameter>, string|null>  $parameters
     */
    public function __invoke(User $user, array $parameters): void
    {
        DB::transaction(function () use ($user, $parameters): void {
            User::query()->lock('for no key update')->findOrFail($user->id);

            DB::table('user_parameters')->where('user_id', $user->id)->delete();

            $rows = [];

            foreach (UserParameter::cases() as $parameter) {
                $value = $parameters[$parameter->value] ?? null;

                if ($value !== null) {
                    $rows[] = ['user_id' => $user->id, 'parameter_id' => $parameter->value, 'value' => $value];
                }
            }

            if ($rows !== []) {
                DB::table('user_parameters')->insert($rows);
            }
        });
    }
}
