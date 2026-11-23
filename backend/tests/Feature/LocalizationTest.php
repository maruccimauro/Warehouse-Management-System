<?php

use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;

/*
| Idioma de las respuestas (SetLocale): español por defecto, Accept-Language
| entre los idiomas soportados y Content-Language en la respuesta. Las
| peticiones de login llevan el Referer de la SPA para tener sesión (ADR-009);
| la validación falla antes de consultar la base.
*/

function loginWithoutData(array $headers = []): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [], ['Referer' => config('app.url').'/login', ...$headers]);
}

it('answers validation errors in spanish by default', function () {
    loginWithoutData()
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'es')
        ->assertJson([
            'code' => 'VALIDATION_FAILED',
            'title' => 'Los datos enviados no son válidos.',
            'errors' => [
                'email' => ['El campo correo electrónico es obligatorio.'],
                'password' => ['El campo contraseña es obligatorio.'],
            ],
        ]);
});

it('answers in english when Accept-Language asks for it', function () {
    loginWithoutData(['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'en')
        ->assertJson([
            'code' => 'VALIDATION_FAILED',
            'title' => 'The given data was invalid.',
            'errors' => ['email' => ['The email field is required.']],
        ]);
});

it('resolves regional variants to the supported language', function () {
    loginWithoutData(['Accept-Language' => 'es-AR,es;q=0.9,en;q=0.8'])
        ->assertHeader('Content-Language', 'es');
});

it('falls back to spanish for an unsupported language', function () {
    loginWithoutData(['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.');
});

it('translates errors raised before reaching a route', function () {
    $this->getJson('/api/v1/ruta-inexistente', ['Accept-Language' => 'en'])
        ->assertNotFound()
        ->assertHeader('Content-Language', 'en')
        ->assertJson(['code' => 'NOT_FOUND', 'detail' => 'The requested resource does not exist.']);

    $this->getJson('/api/v1/ruta-inexistente')
        ->assertJson(['code' => 'NOT_FOUND', 'detail' => 'El recurso solicitado no existe.']);
});

it('varies cached responses by Accept-Language', function () {
    expect(loginWithoutData()->baseResponse->getVary())->toContain('Accept-Language');
});

it('keeps the code untranslated, because it is the contract the client relies on', function () {
    loginWithoutData(['Accept-Language' => 'en'])->assertJsonPath('code', 'VALIDATION_FAILED');
    loginWithoutData()->assertJsonPath('code', 'VALIDATION_FAILED');
});

it('defines every application translation key in all supported languages', function (string $file) {
    $keysFor = fn (string $locale): array => array_keys(Arr::dot(require lang_path("{$locale}/{$file}.php")));
    $spanish = $keysFor('es');

    foreach (config('app.supported_locales') as $locale) {
        expect($keysFor($locale))->toEqualCanonicalizing($spanish);
    }
})->with(['problems', 'identity']);

it('names validation attributes in all supported languages', function () {
    $attributesFor = fn (string $locale): array => array_keys((require lang_path("{$locale}/validation.php"))['attributes']);

    foreach (config('app.supported_locales') as $locale) {
        expect($attributesFor($locale))->toEqualCanonicalizing($attributesFor('es'));
    }
});
