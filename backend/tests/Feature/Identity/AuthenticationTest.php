<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Problems\ProblemDetails;

/*
| Autenticación de la SPA con Sanctum en modo SPA (ADR-009). Las peticiones
| llevan el Referer del frontend: así Sanctum las trata como de un dominio con
| estado y les da sesión por cookie, igual que en el navegador.
*/

function fromSpa(): array
{
    return ['Referer' => config('app.url').'/login'];
}

function credentials(array $overrides = []): array
{
    return [...['email' => 'operador@wms.test', 'password' => 'secreto-correcto'], ...$overrides];
}

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Operador de Prueba',
        'email' => 'operador@wms.test',
        'password' => 'secreto-correcto',
    ]);
});

it('logs in with valid credentials and returns the authenticated user', function () {
    $this->postJson('/api/v1/auth/login', credentials(), fromSpa())
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $this->user->id,
            'name' => 'Operador de Prueba',
            'email' => 'operador@wms.test',
            'locale' => 'es',
            'show_technical_names' => false,
            'password_change_required' => false,
            'roles' => [],
            'permissions' => [],
            'warehouses' => [],
        ]]);

    $this->assertAuthenticatedAs($this->user, 'web');
});

it('accepts the email regardless of case', function () {
    $this->postJson('/api/v1/auth/login', credentials(['email' => 'Operador@WMS.test']), fromSpa())
        ->assertOk();
});

it('regenerates the session id on login to prevent session fixation', function () {
    $this->startSession();
    $before = session()->getId();

    $this->postJson('/api/v1/auth/login', credentials(), fromSpa())->assertOk();

    expect(session()->getId())->not->toBe($before);
});

it('rejects invalid credentials without revealing which field failed', function (array $credentials) {
    $this->postJson('/api/v1/auth/login', $credentials, fromSpa())
        ->assertStatus(422)
        ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
        ->assertJson(['code' => 'INVALID_CREDENTIALS', 'detail' => 'Las credenciales no son válidas.']);

    $this->assertGuest('web');
})->with([
    'contraseña incorrecta' => [credentials(['password' => 'otra'])],
    'email inexistente' => [credentials(['email' => 'nadie@wms.test'])],
]);

it('validates the login payload', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'no-es-un-email'], fromSpa())
        ->assertStatus(422)
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonStructure(['errors' => ['email', 'password']]);
});

it('limits login attempts per account and ip', function () {
    for ($attempt = 1; $attempt <= IdentityServiceProvider::LOGIN_ATTEMPTS_PER_ACCOUNT; $attempt++) {
        $this->postJson('/api/v1/auth/login', credentials(['password' => 'otra']), fromSpa())->assertStatus(422);
    }

    // Ni siquiera la contraseña correcta pasa mientras dura el bloqueo.
    $this->postJson('/api/v1/auth/login', credentials(), fromSpa())
        ->assertStatus(429)
        ->assertJson(['code' => 'TOO_MANY_REQUESTS'])
        ->assertHeader('Retry-After');

    $this->assertGuest('web');
});

it('limits login attempts per ip across different emails', function () {
    for ($attempt = 1; $attempt <= IdentityServiceProvider::LOGIN_ATTEMPTS_PER_IP; $attempt++) {
        $this->postJson('/api/v1/auth/login', credentials(['email' => "usuario{$attempt}@wms.test"]), fromSpa())->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', credentials(), fromSpa())
        ->assertStatus(429)
        ->assertJson(['code' => 'TOO_MANY_REQUESTS']);
});

it('rejects a login that does not come from the frontend', function () {
    $this->postJson('/api/v1/auth/login', credentials())
        ->assertStatus(400)
        ->assertJson(['code' => 'SESSION_REQUIRED']);

    $this->assertGuest('web');
});

it('requires a valid csrf token on stateful requests', function () {
    // Laravel omite la verificación CSRF en el entorno testing; se desactiva esa excepción.
    $this->app['env'] = 'local';

    $this->postJson('/api/v1/auth/login', credentials(), fromSpa())
        ->assertStatus(419)
        ->assertJson(['code' => 'CSRF_TOKEN_MISMATCH', 'title' => 'Page Expired']);
});

it('returns the current user with a session', function () {
    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/auth/me', fromSpa())
        ->assertOk()
        ->assertJsonPath('data.email', 'operador@wms.test');
});

it('responds 401 on a protected route without a session', function () {
    $this->getJson('/api/v1/auth/me', fromSpa())
        ->assertStatus(401)
        ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
        ->assertJson(['code' => 'UNAUTHENTICATED']);
});

it('does not accept bearer tokens', function () {
    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer 1|abcdefghijklmnopqrstuvwxyz'])
        ->assertStatus(401)
        ->assertJson(['code' => 'UNAUTHENTICATED']);
});

it('logs out and invalidates the session', function () {
    $this->postJson('/api/v1/auth/login', credentials(), fromSpa())->assertOk();

    $this->postJson('/api/v1/auth/logout', [], fromSpa())->assertNoContent();

    $this->assertGuest('web');

    // El guard sanctum guarda el usuario de la petición anterior dentro de la misma
    // instancia de la aplicación; en PHP-FPM cada petición empieza de cero.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/auth/me', fromSpa())->assertStatus(401);
});
