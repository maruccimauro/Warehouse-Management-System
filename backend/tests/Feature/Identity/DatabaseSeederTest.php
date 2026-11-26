<?php

use App\Modules\Identity\Database\Seeders\IdentitySeeder;
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
        'username' => 'ADMIN',
        'name' => 'Administrador de desarrollo',
        'email' => 'Admin@WMS.test',
        'password' => 'admin-dev-1',
    ]);
    config()->set('wms.demo_users', 3);
});

it('creates the development admin who can log in', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@wms.test')->sole();

    expect($admin->username)->toBe('ADMIN')
        ->and($admin->name)->toBe('Administrador de desarrollo')
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('admin-dev-1', $admin->password))->toBeTrue();

    $this->postJson(
        '/api/v1/auth/login',
        ['email' => 'admin@wms.test', 'password' => 'admin-dev-1'],
        ['Referer' => config('app.url').'/login'],
    )->assertOk();
});

it('creates the configured number of fictitious users with name, email and code', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('username', 'like', 'U0%')->orderBy('username')->get(['username', 'name', 'email'])->toArray())
        ->toBe([
            ['username' => 'U000001', 'name' => 'María Martínez', 'email' => 'maria.martinez@wms.test'],
            ['username' => 'U000002', 'name' => 'Juan Pérez', 'email' => 'juan.perez@wms.test'],
            ['username' => 'U000003', 'name' => 'Lucía Fernández', 'email' => 'lucia.fernandez@wms.test'],
        ]);
});

it('derives a valid and unique email for every fictitious person', function () {
    $emails = array_map(IdentitySeeder::demoEmail(...), range(1, count(IdentitySeeder::DEMO_USER_NAMES)));

    expect($emails)->each->toMatch('/^[a-z]+\.[a-z]+@wms\.test$/')
        ->and(array_unique($emails))->toHaveCount(count($emails));
});

it('refuses more fictitious users than the list has', function () {
    config()->set('wms.demo_users', count(IdentitySeeder::DEMO_USER_NAMES) + 1);

    $this->seed(DatabaseSeeder::class);
})->throws(LogicException::class, 'wms.demo_users');

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
        ->and($codesOf('maria.martinez@wms.test'))->toBe(['CENTRAL'])
        ->and($codesOf('juan.perez@wms.test'))->toBe(['NORTE'])
        ->and($codesOf('lucia.fernandez@wms.test'))->toBe(['CENTRAL', 'NORTE'])
        ->and($codesOf('martin.gomez@wms.test'))->toBe(['CENTRAL']);
});

it('keeps warehouse assignments changed after seeding', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('email', 'maria.martinez@wms.test')->sole();
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
