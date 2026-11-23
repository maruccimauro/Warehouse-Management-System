<?php

use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Exceptions\ProblemDetailsRenderer;
use App\Shared\Http\Problems\ProblemDetails;
use Illuminate\Support\Facades\Log;
use Tests\Fixtures\Modules\Sample\SampleServiceProvider;

/*
| Contrato de errores de la API (ADR-010): toda respuesta de error es Problem
| Details (RFC 9457) con un code estable.
*/

beforeEach(function () {
    $this->app->register(SampleServiceProvider::class);
    $this->app['router']->getRoutes()->refreshNameLookups();
});

function withDebug(bool $debug): void
{
    config(['app.debug' => $debug]);
    app()->forgetInstance(ProblemDetailsRenderer::class);
}

it('serializes a domain exception as problem details with its code, detail and context', function () {
    $this->getJson('/api/v1/sample/errors/domain')
        ->assertStatus(422)
        ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
        ->assertExactJson([
            'type' => 'urn:wms:problem:sample-rule-violated',
            'title' => 'La regla de ejemplo no se cumple.',
            'status' => 422,
            'detail' => 'Faltan 3 unidades.',
            'instance' => '/api/v1/sample/errors/domain',
            'code' => 'SAMPLE_RULE_VIOLATED',
            'context' => ['available' => 2, 'requested' => 5],
        ]);
});

it('uses the status of the domain exception and its title as default detail', function () {
    $this->getJson('/api/v1/sample/errors/domain-default-detail')
        ->assertStatus(409)
        ->assertJson([
            'code' => 'SAMPLE_CONFLICT',
            'status' => 409,
            'detail' => 'El recurso de ejemplo está en un estado incompatible.',
        ])
        ->assertJsonMissingPath('context');
});

it('does not report domain exceptions as failures', function () {
    Log::spy();

    $this->getJson('/api/v1/sample/errors/domain')->assertStatus(422);

    Log::shouldNotHaveReceived('error');
});

it('maps form request validation to problem details with the field errors', function () {
    $this->postJson('/api/v1/sample/errors/validation', ['quantity' => 0])
        ->assertStatus(422)
        ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
        ->assertJson([
            'type' => 'about:blank',
            'status' => 422,
            'code' => 'VALIDATION_FAILED',
        ])
        ->assertJsonStructure(['errors' => ['name', 'quantity']]);
});

it('maps generic http errors to problem details with a stable code', function (string $uri, int $status, string $code) {
    $this->getJson($uri)
        ->assertStatus($status)
        ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
        ->assertJson(['type' => 'about:blank', 'status' => $status, 'code' => $code]);
})->with([
    'sin sesión' => ['/api/v1/sample/errors/unauthenticated', 401, 'UNAUTHENTICATED'],
    'sin permiso' => ['/api/v1/sample/errors/forbidden', 403, 'FORBIDDEN'],
    'modelo inexistente' => ['/api/v1/sample/errors/model-not-found', 404, 'NOT_FOUND'],
    'ruta inexistente' => ['/api/v1/does-not-exist', 404, 'NOT_FOUND'],
    'límite de solicitudes' => ['/api/v1/sample/errors/throttled', 429, 'TOO_MANY_REQUESTS'],
]);

it('keeps the policy message on 403 and hides internal messages on 404', function () {
    $this->getJson('/api/v1/sample/errors/forbidden')
        ->assertJson(['detail' => 'No tenés permiso sobre este depósito.']);

    $this->getJson('/api/v1/sample/errors/model-not-found')
        ->assertJson(['detail' => 'El recurso solicitado no existe.'])
        ->assertDontSee('SampleRecord');
});

it('responds 405 with the allowed methods', function () {
    $this->postJson('/api/v1/health')
        ->assertStatus(405)
        ->assertJson(['code' => 'METHOD_NOT_ALLOWED'])
        ->assertHeader('Allow');
});

it('keeps the retry after header on 429', function () {
    $this->getJson('/api/v1/sample/errors/throttled')->assertHeader('Retry-After', '60');
});

it('responds 500 without internal details when debug is off', function () {
    withDebug(false);

    $this->getJson('/api/v1/sample/errors/crash')
        ->assertStatus(500)
        ->assertExactJson([
            'type' => 'about:blank',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => 'Ocurrió un error inesperado.',
            'instance' => '/api/v1/sample/errors/crash',
            'code' => 'INTERNAL_ERROR',
        ]);
});

it('adds debug information to 500 only when debug is on', function () {
    withDebug(true);

    $this->getJson('/api/v1/sample/errors/crash')
        ->assertStatus(500)
        ->assertJsonPath('code', 'INTERNAL_ERROR')
        ->assertJsonPath('debug.exception', RuntimeException::class)
        ->assertJsonPath('debug.message', 'Detalle interno que no debe salir.');
});

describe('database errors', function () {
    it('translates a constraint violation by the constraint name', function () {
        $this->getJson('/api/v1/sample/errors/constraint-mapped')
            ->assertStatus(422)
            ->assertJson([
                'type' => 'urn:wms:problem:sample-rule-violated',
                'code' => 'SAMPLE_RULE_VIOLATED',
                'detail' => 'La regla de ejemplo no se cumple.',
            ]);
    });

    it('translates a unique constraint violation to VALUE_ALREADY_EXISTS with the affected field', function () {
        $this->getJson('/api/v1/sample/errors/unique-mapped')
            ->assertStatus(422)
            ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
            ->assertExactJson([
                'type' => 'urn:wms:problem:value-already-exists',
                'title' => 'El valor ya está registrado.',
                'status' => 422,
                'detail' => 'El campo código ya ha sido registrado.',
                'instance' => '/api/v1/sample/errors/unique-mapped',
                'code' => 'VALUE_ALREADY_EXISTS',
                'context' => ['field' => 'code'],
            ]);
    });

    it('writes VALUE_ALREADY_EXISTS in the language of the request', function () {
        $this->getJson('/api/v1/sample/errors/unique-mapped', ['Accept-Language' => 'en'])
            ->assertStatus(422)
            ->assertJson([
                'title' => 'The value is already registered.',
                'detail' => 'The code has already been taken.',
                'context' => ['field' => 'code'],
            ]);
    });

    it('translates a trigger exception by its message', function () {
        $this->getJson('/api/v1/sample/errors/trigger-mapped')
            ->assertStatus(409)
            ->assertJson(['code' => 'SAMPLE_CONFLICT']);
    });

    it('does not report translated database errors as failures', function () {
        Log::spy();

        $this->getJson('/api/v1/sample/errors/constraint-mapped')->assertStatus(422);

        Log::shouldNotHaveReceived('error');
    });

    it('responds 500 without details for an untranslated database error', function (string $uri, string $internal) {
        withDebug(false);

        $this->getJson($uri)
            ->assertStatus(500)
            ->assertJson(['code' => 'INTERNAL_ERROR'])
            ->assertDontSee($internal)
            ->assertDontSee('sample_items');
    })->with([
        'restricción sin traducción' => ['/api/v1/sample/errors/constraint-unmapped', 'sample_unmapped_check'],
        'trigger sin traducción' => ['/api/v1/sample/errors/trigger-unmapped', 'SAMPLE_UNMAPPED_TRIGGER'],
    ]);

    it('exposes the registered translations for the coverage check of E3', function () {
        $translator = $this->app->make(DatabaseErrorTranslator::class);

        expect($translator->mappedConstraints())->toContain('sample_quantity_check', 'sample_items_code_unique')
            ->and($translator->mappedTriggerCodes())->toContain('SAMPLE_TRIGGER_CONFLICT');
    });
});
