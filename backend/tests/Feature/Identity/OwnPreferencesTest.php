<?php

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Http\Requests\UpdateOwnFavoritesRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Preferencias propias en el servidor (E2.21, ADR-016): parámetros de SU3 como
| USR05 de SAP, favoritos con orden explícito, idioma y mostrar códigos (D5),
| con reemplazo completo en parámetros y favoritos (D6).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->central = app(CreateWarehouse::class)('CEN', 'Central');
    $this->north = app(CreateWarehouse::class)('NOR', 'Norte');
    $this->user = userWithAccess(Role::Operator, [$this->central]);
});

describe('parameters', function () {
    it('lists every supported parameter, null when it has no value', function () {
        $this->actingAs($this->user)
            ->getJson('/api/v1/me/parameters')
            ->assertOk()
            ->assertExactJson(['data' => ['LGN' => null]]);
    });

    it('stores the default warehouse and returns the saved set', function () {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/parameters', ['LGN' => 'CEN'])
            ->assertOk()
            ->assertExactJson(['data' => ['LGN' => 'CEN']]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/me/parameters')
            ->assertJsonPath('data.LGN', 'CEN');
    });

    it('removes a parameter sent as null', function () {
        DB::table('user_parameters')->insert(['user_id' => $this->user->id, 'parameter_id' => 'LGN', 'value' => 'CEN']);

        $this->actingAs($this->user)
            ->putJson('/api/v1/me/parameters', ['LGN' => null])
            ->assertOk()
            ->assertJsonPath('data.LGN', null);

        expect(DB::table('user_parameters')->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('rejects a warehouse where the user does not operate', function () {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/parameters', ['LGN' => 'NOR'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['LGN'], 'errors');
    });

    it('requires every parameter in the body, because the set is replaced', function () {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/parameters', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['LGN'], 'errors');
    });

    it('does not mix parameters between users', function () {
        $other = userWithAccess(Role::Operator, [$this->north]);

        $this->actingAs($other)->putJson('/api/v1/me/parameters', ['LGN' => 'NOR'])->assertOk();

        $this->actingAs($this->user)
            ->getJson('/api/v1/me/parameters')
            ->assertJsonPath('data.LGN', null);
    });

    it('rejects an unsupported parameter id at the database level', function () {
        expect(fn () => DB::table('user_parameters')
            ->insert(['user_id' => $this->user->id, 'parameter_id' => 'WRK', 'value' => '1000']))
            ->toThrow(QueryException::class, 'user_parameters_parameter_id_check');
    });
});

describe('favorites', function () {
    it('stores the favorites in the given order', function () {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/favorites', ['favorites' => ['SU3', 'MM03', 'LS03N']])
            ->assertOk()
            ->assertExactJson(['data' => ['SU3', 'MM03', 'LS03N']]);

        $this->actingAs($this->user)
            ->putJson('/api/v1/me/favorites', ['favorites' => ['LS03N', 'SU3']])
            ->assertExactJson(['data' => ['LS03N', 'SU3']]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/me/favorites')
            ->assertExactJson(['data' => ['LS03N', 'SU3']]);
    });

    it('clears the favorites with an empty list', function () {
        $this->actingAs($this->user)->putJson('/api/v1/me/favorites', ['favorites' => ['SU3']])->assertOk();

        $this->actingAs($this->user)
            ->putJson('/api/v1/me/favorites', ['favorites' => []])
            ->assertExactJson(['data' => []]);
    });

    it('rejects duplicated codes and codes with an invalid format', function (array $favorites, string $field) {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/favorites', ['favorites' => $favorites])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'duplicated' => [['SU3', 'SU3'], 'favorites.0'],
        'lowercase' => [['su3'], 'favorites.0'],
        'not a list' => [['a' => 'SU3'], 'favorites'],
    ]);

    it('limits the number of favorites', function () {
        $codes = array_map(fn (int $index): string => 'ZT'.$index, range(1, UpdateOwnFavoritesRequest::MAX_FAVORITES + 1));

        $this->actingAs($this->user)
            ->putJson('/api/v1/me/favorites', ['favorites' => $codes])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['favorites'], 'errors');
    });
});

describe('settings', function () {
    it('updates the language and the technical names option and returns the user', function () {
        $this->actingAs($this->user)
            ->patchJson('/api/v1/me', ['locale' => 'en', 'show_technical_names' => true])
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.show_technical_names', true);

        $user = User::query()->find($this->user->id);

        expect($user?->locale)->toBe('en')->and($user?->show_technical_names)->toBeTrue();
    });

    it('keeps the keys that are not sent', function () {
        $this->actingAs($this->user)
            ->patchJson('/api/v1/me', ['show_technical_names' => true])
            ->assertOk()
            ->assertJsonPath('data.locale', 'es');
    });

    it('rejects an unsupported language', function () {
        $this->actingAs($this->user)
            ->patchJson('/api/v1/me', ['locale' => 'pt'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale'], 'errors');
    });

    it('exposes both settings in GET /auth/me', function () {
        $this->actingAs($this->user)
            ->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.show_technical_names', false);
    });
});

describe('preferred language', function () {
    it('answers in the user language over Accept-Language', function () {
        $this->user->locale = 'en';
        $this->user->save();

        $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'es')
            ->patchJson('/api/v1/me', ['locale' => 'pt'])
            ->assertStatus(422)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('errors.locale.0', 'The selected language is invalid.');
    });

    it('keeps Accept-Language for requests without a session', function () {
        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Language', 'en');
    });
});

it('blocks the preferences while a password change is pending', function () {
    $this->user->password_change_required = true;
    $this->user->save();

    $this->actingAs($this->user)
        ->getJson('/api/v1/me/favorites')
        ->assertForbidden()
        ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
});
