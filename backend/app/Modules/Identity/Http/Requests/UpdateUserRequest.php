<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Shared\Http\Requests\Concerns\ResolvesRouteModel;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

/**
 * Edición de un usuario (SU01, D6): cada clave ausente queda como está; roles
 * y depósitos, si vienen, reemplazan la asignación completa. password resetea
 * la contraseña, que vuelve a ser inicial (REG-021).
 */
class UpdateUserRequest extends StoreUserRequest
{
    use ResolvesRouteModel;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->targetUser());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', ...self::emailRules($this->targetUser())],
            'password' => ['sometimes', 'required', 'string', Password::defaults()],
            'locale' => self::localeRules(),
            'is_active' => ['sometimes', 'required', 'boolean'],
            ...self::assignmentRules(required: false),
        ];
    }

    protected function passedValidation(): void
    {
        $roles = $this->roles();

        if ($roles !== null) {
            Gate::authorize('assignRoles', [User::class, $this->targetUser(), $roles]);
        }
    }

    /**
     * Usuario de la ruta. No se llama user(): FormRequest::user() es el
     * usuario autenticado.
     */
    public function targetUser(): User
    {
        return $this->routeModel('user', User::class);
    }
}
