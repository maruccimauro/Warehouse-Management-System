<?php

use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
| Tabla users alineada con database-design.md (E2.19).
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function insertUserRow(array $overrides = []): void
{
    DB::table('users')->insert([
        'username' => 'USUARIO',
        'name' => 'Usuario',
        'email' => 'usuario@wms.test',
        'password' => Hash::make('secreto'),
        ...$overrides,
    ]);
}

it('creates active users in spanish by default', function () {
    insertUserRow();

    $user = User::query()->sole();

    expect($user->is_active)->toBeTrue()
        ->and($user->locale)->toBe('es')
        ->and($user->created_at)->not->toBeNull();
});

it('stores the email in lowercase through the model', function () {
    $user = User::factory()->create(['email' => 'Operador@WMS.Test']);

    expect($user->refresh()->email)->toBe('operador@wms.test');
});

it('rejects an uppercase email at the database level', function () {
    insertUserRow(['email' => 'Operador@WMS.test']);
})->throws(QueryException::class, 'users_email_lowercase_check');

it('rejects a malformed locale at the database level', function () {
    insertUserRow(['locale' => 'español']);
})->throws(QueryException::class, 'users_locale_format_check');

it('stores the username in uppercase through the model', function () {
    $user = User::factory()->create(['username' => 'jperez']);

    expect($user->refresh()->username)->toBe('JPEREZ');
});

it('rejects a malformed username at the database level', function (string $username) {
    insertUserRow(['username' => $username]);
})->with(['minúsculas' => 'jperez', 'con punto' => 'J.PEREZ', 'vacío' => ''])
    ->throws(QueryException::class, 'users_username_format_check');

it('rejects a duplicated username at the database level', function () {
    insertUserRow();
    insertUserRow(['email' => 'otro@wms.test']);
})->throws(QueryException::class, 'users_username_unique');

it('gives the existing users a unique username when adding the column', function () {
    $migration = require database_path('../app/Modules/Identity/Database/Migrations/2026_11_25_100000_add_username_to_users.php');
    $migration->down();
    DB::table('users')->insert([
        ['name' => 'Uno', 'email' => 'uno@wms.test', 'password' => 'x'],
        ['name' => 'Dos', 'email' => 'dos@wms.test', 'password' => 'x'],
    ]);

    $migration->up();

    $ids = DB::table('users')->orderBy('id')->pluck('id')->all();
    expect(DB::table('users')->orderBy('id')->pluck('username')->all())
        ->toBe(array_map(fn (int $id): string => 'U'.str_pad((string) $id, 6, '0', STR_PAD_LEFT), $ids));
});

it('limits the name to 120 characters', function () {
    insertUserRow(['name' => str_repeat('a', 121)]);
})->throws(QueryException::class);
