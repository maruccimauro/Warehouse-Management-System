<?php

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Authorization\Role;

/**
 * Cambios de un PATCH /users/{id} (D6), como la ficha de SU01 que se graba
 * entera. Cada campo nulo significa "no se toca"; roles y depósitos, cuando
 * vienen, reemplazan la asignación completa.
 */
final readonly class UserChanges
{
    /**
     * @param  array{name?: string, email?: string, locale?: string}  $attributes
     * @param  list<Role>|null  $roles
     * @param  list<int>|null  $warehouseIds
     */
    public function __construct(
        public array $attributes = [],
        public ?bool $isActive = null,
        public ?array $roles = null,
        public ?array $warehouseIds = null,
        public ?string $password = null,
    ) {}

    /**
     * Si el cambio puede achicar el conjunto de administradores activos
     * (REG-022): cambiar roles o desactivar.
     */
    public function mayRemoveAdministrator(): bool
    {
        return $this->roles !== null || $this->isActive === false;
    }
}
