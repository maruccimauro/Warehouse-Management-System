<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $username
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property bool $password_change_required
 * @property string $locale
 * @property bool $show_technical_names
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['username', 'name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Los mismos valores por defecto que la base (database-design.md): sin
     * ellos, un usuario recién creado no tiene is_active en memoria hasta
     * releerlo, y EnsureUserIsActive lo tomaría como inactivo.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'password_change_required' => false,
        'locale' => 'es',
        'show_technical_names' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'password_change_required' => 'boolean',
            'show_technical_names' => 'boolean',
        ];
    }

    /**
     * Depósitos asignados (user_warehouse, REG-005): donde el usuario opera.
     * El alcance completo, con los permisos warehouses.view_all y
     * warehouses.operate_all, lo resuelve WarehouseAccess.
     *
     * @return BelongsToMany<Warehouse, $this>
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse');
    }

    /**
     * El email se guarda en minúsculas: la base lo exige
     * (users_email_lowercase_check) y así la unicidad no depende de cómo se
     * tipeó.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower($value));
    }

    /**
     * El código de usuario se guarda en mayúsculas (users_username_format_check,
     * E2.14 D1), como el nombre de usuario de SU01.
     *
     * @return Attribute<string, string>
     */
    protected function username(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtoupper($value));
    }
}
