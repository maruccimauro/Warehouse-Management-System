<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Exceptions\CriticalRoleConfirmationRequired;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta de usuario con users.manage (SU01, D6): código de usuario (E2.14 D1),
 * datos, contraseña inicial,
 * roles y depósitos en una sola petición. Nace activo y con cambio de
 * contraseña obligatorio (REG-021). Los roles pedidos se autorizan después de
 * validar, contra los permisos de quien opera (REG-023), y cada rol crítico
 * que se suma exige su confirmación en confirmed_critical_roles (REG-025).
 */
class StoreUserRequest extends FormRequest
{
    /** Mayúsculas y dígitos, hasta 12 caracteres (users_username_format_check). */
    public const USERNAME_PATTERN = '/^[A-Z0-9]{1,12}$/';

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
            'username' => ['required', 'string', 'regex:'.self::USERNAME_PATTERN, Rule::unique('users', 'username')],
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
        $this->ensureCriticalRolesConfirmed([]);
    }

    /**
     * Cada rol crítico pedido que el usuario no tenía tiene que venir en
     * confirmed_critical_roles: es la constancia de que quien asigna escribió
     * la frase de confirmación (D16.4). Va después de autorizar: sin permiso
     * para asignar el rol, la respuesta es 403.
     *
     * @param  list<string>  $currentRoles
     *
     * @throws CriticalRoleConfirmationRequired
     */
    protected function ensureCriticalRolesConfirmed(array $currentRoles): void
    {
        $confirmed = $this->validated('confirmed_critical_roles', []);
        $confirmed = is_array($confirmed) ? $confirmed : [];

        $missing = array_values(array_filter(
            $this->roles() ?? [],
            fn (Role $role): bool => $role->isCritical()
                && ! in_array($role->value, $currentRoles, true)
                && ! in_array($role->value, $confirmed, true),
        ));

        if ($missing !== []) {
            throw CriticalRoleConfirmationRequired::for($missing);
        }
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
            'confirmed_critical_roles' => ['sometimes', 'array', 'list', 'max:'.count(Role::critical())],
            'confirmed_critical_roles.*' => ['required', 'string', 'distinct', Rule::enum(Role::class)->only(Role::critical())],
            'warehouse_ids' => [$presence, 'array', 'list', 'max:500'],
            'warehouse_ids.*' => ['required', 'integer', 'distinct', 'min:1', Rule::exists('warehouses', 'id')],
        ];
    }

    /**
     * Email sin espacios y en minúsculas, y código de usuario sin espacios y
     * en mayúsculas, para que la regla unique compare con lo que se va a
     * guardar (users_email_lowercase_check, users_username_format_check).
     */
    protected function normalizeUserFields(): void
    {
        $username = $this->input('username');

        if (is_string($username)) {
            $this->merge(['username' => mb_strtoupper(trim($username))]);
        }

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
