<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Identity\Actions\ChangeOwnPassword;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\RecordingAuditLogger;

/*
| Estado de la cuenta en el login y en cada petición (E2.09): un usuario
| desactivado no inicia sesión ni conserva las abiertas (REG-020, D1, D2), y
| una contraseña inicial o reseteada solo deja cambiarla (REG-021, D5).
*/

function spaHeaders(): array
{
    return ['Referer' => config('app.url').'/login'];
}

beforeEach(function () {
    $this->audit = new RecordingAuditLogger;
    app()->instance(AuditLogger::class, $this->audit);

    $this->user = User::factory()->create([
        'email' => 'cuenta@wms.test',
        'password' => 'clave-actual-segura',
    ]);
});

describe('inactive user', function () {
    beforeEach(function () {
        $this->user->is_active = false;
        $this->user->save();
    });

    it('rejects the login with the correct password as USER_INACTIVE', function () {
        $this->postJson('/api/v1/auth/login', ['email' => 'cuenta@wms.test', 'password' => 'clave-actual-segura'], spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'USER_INACTIVE');

        $this->assertGuest('web');
    });

    it('does not reveal the state with a wrong password', function () {
        $this->postJson('/api/v1/auth/login', ['email' => 'cuenta@wms.test', 'password' => 'otra-clave'], spaHeaders())
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    });

    it('closes a session that was already open with 401', function () {
        $this->actingAs($this->user)
            ->getJson('/api/v1/auth/me', spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->assertGuest('web');
    });
});

describe('password change required', function () {
    beforeEach(function () {
        $this->user->password_change_required = true;
        $this->user->save();
    });

    it('reports it in the authenticated user', function () {
        $this->actingAs($this->user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.password_change_required', true);
    });

    it('blocks the rest of the api with PASSWORD_CHANGE_REQUIRED', function (string $uri) {
        $this->actingAs($this->user)
            ->getJson($uri)
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    })->with([
        'depósitos' => ['/api/v1/warehouses'],
        'productos' => ['/api/v1/products'],
        'usuarios' => ['/api/v1/users'],
    ]);

    it('allows logging out', function () {
        $this->actingAs($this->user)->postJson('/api/v1/auth/logout', [], spaHeaders())->assertNoContent();
    });

    it('lifts the restriction after changing the password', function () {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'clave-actual-segura',
                'password' => 'clave-nueva-segura',
                'password_confirmation' => 'clave-nueva-segura',
            ])
            ->assertNoContent();

        $user = $this->user->fresh();
        expect($user?->password_change_required)->toBeFalse()
            ->and(Hash::check('clave-nueva-segura', (string) $user?->password))->toBeTrue()
            ->and($this->audit->records[0]['action'])->toBe('user.password_changed');

        $this->app['auth']->forgetGuards();
        $this->actingAs($user ?? $this->user)->getJson('/api/v1/warehouses')->assertOk();
    });
});

describe('own password change', function () {
    it('rejects invalid changes', function (array $payload, string $field) {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'clave-actual-segura',
                'password' => 'clave-nueva-segura',
                'password_confirmation' => 'clave-nueva-segura',
                ...$payload,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');

        expect(Hash::check('clave-actual-segura', (string) $this->user->fresh()?->password))->toBeTrue();
    })->with([
        'contraseña actual incorrecta' => [['current_password' => 'no-es-la-actual'], 'current_password'],
        'nueva corta' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
        'sin confirmar' => [['password_confirmation' => 'otra-distinta-1'], 'password'],
        'igual a la actual' => [['password' => 'clave-actual-segura', 'password_confirmation' => 'clave-actual-segura'], 'password'],
    ]);

    it('limits the attempts per user', function () {
        for ($attempt = 1; $attempt <= IdentityServiceProvider::PASSWORD_CHANGE_ATTEMPTS; $attempt++) {
            $this->actingAs($this->user)
                ->putJson('/api/v1/me/password', ['current_password' => 'no-es-la-actual', 'password' => 'clave-nueva-segura', 'password_confirmation' => 'clave-nueva-segura'])
                ->assertStatus(422);
        }

        $this->actingAs($this->user)
            ->putJson('/api/v1/me/password', ['current_password' => 'clave-actual-segura', 'password' => 'clave-nueva-segura', 'password_confirmation' => 'clave-nueva-segura'])
            ->assertStatus(429);
    });

    it('closes the other sessions and keeps the current one', function () {
        config(['session.driver' => 'database']);

        foreach (['actual', 'otra'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);
        }

        app(ChangeOwnPassword::class)($this->user, 'clave-nueva-segura', 'actual');

        expect(DB::table('sessions')->pluck('id')->all())->toBe(['actual']);
    });

    it('requires a session', function () {
        $this->putJson('/api/v1/me/password', [])->assertUnauthorized();
    });
});
