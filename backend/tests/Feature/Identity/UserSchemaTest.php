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

it('limits the name to 120 characters', function () {
    insertUserRow(['name' => str_repeat('a', 121)]);
})->throws(QueryException::class);
