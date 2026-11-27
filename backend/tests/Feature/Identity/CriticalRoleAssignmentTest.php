<?php

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Actions\UpdateUser;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Data\UserChanges;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\CriticalRoleNotifier;
use App\Modules\Inbox\Models\InboxMessage;

/*
| Roles críticos (E3.28, REG-025): asignar Administrador, Gerente de
| operaciones o Jefe de operaciones exige la confirmación explícita en el
| cuerpo y avisa en la bandeja a todos los Administradores activos, incluido
| quien asignó.
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->admin = userWithAccess(Role::Administrator);
    $this->otherAdmin = userWithAccess(Role::Administrator);
    $this->inactiveAdmin = userWithAccess(Role::Administrator);
    $this->inactiveAdmin->forceFill(['is_active' => false])->save();
    $this->target = userWithAccess(Role::Operator);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function criticalUserPayload(array $overrides = []): array
{
    return [
        'username' => 'NUEVOADM',
        'name' => 'Nuevo Administrador',
        'email' => 'nuevo.admin@wms.test',
        'password' => 'clave-inicial-segura',
        'roles' => [Role::Administrator->value],
        'warehouse_ids' => [],
        ...$overrides,
    ];
}

/**
 * @return list<int>
 */
function criticalNoticeRecipients(): array
{
    return InboxMessage::query()->where('type', CriticalRoleNotifier::MESSAGE_TYPE)->orderBy('recipient_id')->pluck('recipient_id')->all();
}

describe('confirmation', function () {
    it('rejects assigning a critical role without its confirmation, listing the missing roles', function (Role $role) {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->target->id}", ['roles' => [Role::Operator->value, $role->value]])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CRITICAL_ROLE_CONFIRMATION_REQUIRED')
            ->assertJsonPath('context.roles', [$role->value]);

        expect($this->target->fresh()?->hasRole($role->value))->toBeFalse()
            ->and(InboxMessage::query()->count())->toBe(0);
    })->with([
        'administrador' => [Role::Administrator],
        'gerente de operaciones' => [Role::OperationsManager],
        'jefe de operaciones' => [Role::OperationsLead],
    ]);

    it('requires the confirmation when creating a user', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', criticalUserPayload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'CRITICAL_ROLE_CONFIRMATION_REQUIRED');

        expect(User::query()->where('username', 'NUEVOADM')->exists())->toBeFalse();
    });

    it('lists only the critical roles that are missing their confirmation', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->target->id}", [
                'roles' => [Role::Administrator->value, Role::OperationsManager->value],
                'confirmed_critical_roles' => [Role::Administrator->value],
            ])
            ->assertStatus(422)
            ->assertJsonPath('context.roles', [Role::OperationsManager->value]);
    });

    it('does not ask again for a critical role the user already had', function () {
        $this->target->syncRoles([Role::OperationsLead->value]);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->target->id}", ['roles' => [Role::OperationsLead->value, Role::Auditor->value]])
            ->assertOk();

        expect(InboxMessage::query()->count())->toBe(0);
    });

    it('does not ask for confirmation of non critical roles', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->target->id}", ['roles' => [Role::Supervisor->value]])
            ->assertOk();
    });

    it('answers 403 before asking for the confirmation when the role cannot be assigned', function () {
        $this->actingAs(userWithAccess(Role::Auditor))
            ->patchJson("/api/v1/users/{$this->target->id}", ['roles' => [Role::Administrator->value]])
            ->assertForbidden();
    });

    it('rejects confirming a role that is not critical', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->target->id}", [
                'roles' => [Role::Supervisor->value],
                'confirmed_critical_roles' => [Role::Supervisor->value],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirmed_critical_roles.0'], 'errors');
    });
});

describe('notice', function () {
    it('notifies every active administrator, including who assigned it, when confirmed', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->target->id}", [
                'roles' => [Role::Administrator->value],
                'confirmed_critical_roles' => [Role::Administrator->value],
            ])
            ->assertOk();

        // El usuario que pasa a ser Administrador también recibe el aviso.
        $expected = [$this->admin->id, $this->otherAdmin->id, $this->target->id];
        sort($expected);

        $message = InboxMessage::query()->where('recipient_id', $this->otherAdmin->id)->firstOrFail();

        expect(criticalNoticeRecipients())->toBe($expected)
            // JSONB no conserva el orden de las claves.
            ->and($message->parameters)->toEqual([
                'username' => $this->target->username,
                'name' => $this->target->name,
                'role' => Role::Administrator->value,
                'assigned_by' => $this->admin->username,
            ])
            ->and($message->subject_type)->toBe('user')
            ->and($message->subject_id)->toBe($this->target->id);
    });

    it('sends one notice per critical role added', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->target->id}", [
                'roles' => [Role::OperationsManager->value, Role::OperationsLead->value],
                'confirmed_critical_roles' => [Role::OperationsManager->value, Role::OperationsLead->value],
            ])
            ->assertOk();

        $roles = InboxMessage::query()->where('recipient_id', $this->admin->id)->get()->map(fn (InboxMessage $message): mixed => $message->parameters['role'] ?? null)->sort()->values()->all();

        expect($roles)->toBe([Role::OperationsLead->value, Role::OperationsManager->value]);
    });

    it('notifies when creating a user with a critical role', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', criticalUserPayload(['confirmed_critical_roles' => [Role::Administrator->value]]))
            ->assertCreated();

        $created = User::query()->where('username', 'NUEVOADM')->firstOrFail();

        expect(criticalNoticeRecipients())->toContain($this->admin->id, $this->otherAdmin->id, $created->id)
            ->not->toContain($this->inactiveAdmin->id);
    });

    it('is sent by the action itself, which does not depend on the HTTP confirmation', function () {
        app(UpdateUser::class)($this->admin, $this->target, new UserChanges(roles: [Role::OperationsLead]));

        $expected = [$this->admin->id, $this->otherAdmin->id];
        sort($expected);

        expect(criticalNoticeRecipients())->toBe($expected);
    });
});
