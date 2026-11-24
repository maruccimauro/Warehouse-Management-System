<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta de usuario con users.manage (SU01, D6): datos, contraseña inicial,
 * roles y depósitos en una sola petición. Nace activo y con cambio de
 * contraseña obligatorio (REG-021). Los roles pedidos se autorizan después de
 * validar, contra los permisos de quien opera (REG-023).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUserFields();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => self::emailRules(),
            'password' => ['required', 'string', Password::defaults()],
            'locale' => self::localeRules(),
            'is_active' => ['prohibited'],
            ...self::assignmentRules(required: true),
        ];
    }

    protected function passedValidation(): void
    {
        Gate::authorize('assignRoles', [User::class, null, $this->roles() ?? []]);
    }

    /**
     * Roles pedidos, ya validados; null si la petición no los trae.
     *
     * @return list<Role>|null
     */
    public function roles(): ?array
    {
        if (! $this->has('roles')) {
            return null;
        }

        $roles = $this->validated('roles');

        return array_values(array_map(
            fn (mixed $role): Role => Role::from(is_string($role) ? $role : ''),
            is_array($roles) ? $roles : [],
        ));
    }

    /**
     * Depósitos pedidos, ya validados; null si la petición no los trae.
     *
     * @return list<int>|null
     */
    public function warehouseIds(): ?array
    {
        if (! $this->has('warehouse_ids')) {
            return null;
        }

        $ids = $this->validated('warehouse_ids');

        return array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($ids) ? $ids : []));
    }

    /**
     * @return list<mixed>
     */
    protected static function emailRules(?User $ignore = null): array
    {
        return ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore?->id)];
    }

    /**
     * @return list<mixed>
     */
    protected static function localeRules(): array
    {
        return ['sometimes', 'required', 'string', Rule::in(config()->array('app.supported_locales'))];
    }

    /**
     * Roles y depósitos como reemplazo completo (D6). Se admiten depósitos
     * inactivos: asignarlos es mantenimiento de datos maestros.
     *
     * @return array<string, list<mixed>>
     */
    protected static function assignmentRules(bool $required): array
    {
        $presence = $required ? 'present' : 'sometimes';

        return [
            'roles' => [$presence, 'array', 'list', 'max:'.count(Role::cases())],
            'roles.*' => ['required', 'string', 'distinct', Rule::enum(Role::class)],
            'warehouse_ids' => [$presence, 'array', 'list', 'max:500'],
            'warehouse_ids.*' => ['required', 'integer', 'distinct', 'min:1', Rule::exists('warehouses', 'id')],
        ];
    }

    /**
     * Email sin espacios y en minúsculas, para que la regla unique compare con
     * lo que se va a guardar (users_email_lowercase_check).
     */
    protected function normalizeUserFields(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }

        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
