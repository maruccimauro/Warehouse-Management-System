<?php

use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

/*
| Seeders de desarrollo (E1.12): el entrypoint los corre en cada arranque, por
| eso tienen que ser idempotentes y negarse a correr fuera de local y testing.
*/

beforeEach(function () {
    config()->set('wms.dev_admin', [
        'name' => 'Administrador de desarrollo',
        'email' => 'Admin@WMS.test',
        'password' => 'admin-dev-1',
    ]);
    config()->set('wms.demo_users', 3);
});

it('creates the development admin who can log in', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@wms.test')->sole();

    expect($admin->name)->toBe('Administrador de desarrollo')
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('admin-dev-1', $admin->password))->toBeTrue();

    $this->postJson(
        '/api/v1/auth/login',
        ['email' => 'admin@wms.test', 'password' => 'admin-dev-1'],
        ['Referer' => config('app.url').'/login'],
    )->assertOk();
});

it('creates the configured number of fictitious users with predictable emails', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'like', 'usuario%@wms.test')->orderBy('email')->pluck('email')->all())
        ->toBe(['usuario01@wms.test', 'usuario02@wms.test', 'usuario03@wms.test']);
});

it('is idempotent and keeps passwords changed after seeding', function () {
    $this->seed(DatabaseSeeder::class);
    User::query()->where('email', 'admin@wms.test')->sole()->update(['password' => 'cambiada-a-mano']);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(4)
        ->and(Hash::check('cambiada-a-mano', User::query()->where('email', 'admin@wms.test')->sole()->password))->toBeTrue();
});

it('assigns warehouses to the fictitious users in cycle and none to the admin', function () {
    config()->set('wms.demo_users', 4);
    $this->seed(DatabaseSeeder::class);

    $codesOf = fn (string $email): array => User::query()->where('email', $email)->sole()
        ->warehouses()->orderBy('code')->pluck('code')->all();

    expect($codesOf('admin@wms.test'))->toBe([])
        ->and($codesOf('usuario01@wms.test'))->toBe(['CENTRAL'])
        ->and($codesOf('usuario02@wms.test'))->toBe(['NORTE'])
        ->and($codesOf('usuario03@wms.test'))->toBe(['CENTRAL', 'NORTE'])
        ->and($codesOf('usuario04@wms.test'))->toBe(['CENTRAL']);
});

it('keeps warehouse assignments changed after seeding', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('email', 'usuario01@wms.test')->sole();
    $user->warehouses()->sync(Warehouse::query()->where('code', 'NORTE')->pluck('id')->all());

    $this->seed(DatabaseSeeder::class);

    expect($user->warehouses()->pluck('code')->all())->toBe(['NORTE']);
});

it('refuses to run outside local and testing', function () {
    app()->detectEnvironment(fn (): string => 'production');

    // Se invoca el seeder directo: db:seed en producción ya exige --force, y la
    // guarda tiene que frenar también un db:seed --force.
    expect(fn () => app(DatabaseSeeder::class)->setContainer(app())->__invoke())
        ->toThrow(RuntimeException::class, 'Los seeders de desarrollo solo corren en local y testing; el entorno actual es production.');

    expect(User::query()->count())->toBe(0);
});
