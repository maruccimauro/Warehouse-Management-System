<?php

use App\Providers\ApiDocumentationServiceProvider;
use App\Shared\Exceptions\ProblemDetailsRenderer;
use App\Shared\Http\Idempotency\Idempotency;
use App\Shared\Http\Problems\ProblemDetails;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

/*
| Especificación OpenAPI (ADR-012, E2.11): las respuestas de error documentadas
| son las que produce ProblemDetailsRenderer (ADR-010), con los códigos que
| cada operación puede devolver, y todo código documentado está en el
| catálogo de business-rules.md o entre los genéricos de ADR-010.
*/

/**
 * Generar el documento lleva segundos: se reutiliza entre pruebas salvo que
 * la prueba agregue rutas.
 *
 * @return array<string, mixed>
 */
function openapi_spec(bool $fresh = false): array
{
    static $cached = null;

    if ($fresh || $cached === null) {
        /** @var array<string, mixed> $spec */
        $spec = app(Generator::class)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

        if ($fresh) {
            return $spec;
        }

        $cached = $spec;
    }

    return $cached;
}

/**
 * Códigos documentados por estado HTTP en una operación.
 *
 * @param  array<string, mixed>  $spec
 * @return array<string, list<string>>
 */
function documented_codes(array $spec, string $method, string $path): array
{
    $responses = $spec['paths'][$path][$method]['responses'] ?? null;
    expect($responses)->toBeArray("No está documentada la operación {$method} {$path}.");

    $codes = [];

    foreach ($responses as $status => $response) {
        $schema = $response['content'][ProblemDetails::CONTENT_TYPE]['schema'] ?? null;

        if ($schema === null) {
            continue;
        }

        foreach ($schema['anyOf'] ?? [$schema] as $variant) {
            if (isset($variant['$ref'])) {
                $variant = $spec['components']['schemas'][basename($variant['$ref'])];
            }

            foreach ($variant['allOf'] ?? [] as $part) {
                if (isset($part['properties']['code']['const'])) {
                    $codes[(string) $status][] = $part['properties']['code']['const'];
                }
            }
        }
    }

    return $codes;
}

/**
 * Código => estado del catálogo de business-rules.md.
 *
 * @return array<string, int>
 */
function business_rule_codes(): array
{
    $catalog = file_get_contents(base_path('../docs/02-analysis/business-rules.md'));

    if ($catalog === false) {
        return [];
    }

    preg_match_all('/^\| `([A-Z_]+)` \| (\d{3}) \|/m', $catalog, $matches, PREG_SET_ORDER);

    $codes = [];
    foreach ($matches as [, $code, $status]) {
        $codes[$code] = (int) $status;
    }

    return $codes;
}

it('describes the versioned API with a relative server and the shared problem schema', function () {
    $spec = openapi_spec();

    expect($spec['servers'])->toBe([['url' => '/api/v1']])
        ->and($spec['components']['schemas'])->toHaveKey('ProblemDetails')
        ->and($spec['components']['securitySchemes'])->toHaveKeys(['sessionCookie', 'xsrfToken'])
        ->and(array_keys($spec['paths']))->each->not->toStartWith('/docs');
});

it('documents every client error as problem details, never with the default Laravel format', function () {
    $spec = openapi_spec();

    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            foreach ($operation['responses'] as $status => $response) {
                // Los 5xx documentados son el 503 de /health, con su cuerpo propio
                // para monitores; un 500 nunca se documenta.
                if ((int) $status < 400 || (int) $status >= 500) {
                    continue;
                }

                expect(array_keys($response['content'] ?? []))
                    ->toBe([ProblemDetails::CONTENT_TYPE], "{$method} {$path} documenta {$status} con otro formato.");
            }
        }
    }
});

it('documents only codes of the domain catalog or the generic codes, with their status', function () {
    $catalog = business_rule_codes();
    expect($catalog)->not->toBeEmpty();

    $generic = array_flip(ProblemDetailsRenderer::HTTP_CODES) + ['VALIDATION_FAILED' => 422];
    $spec = openapi_spec();

    foreach ($spec['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            foreach (documented_codes($spec, $method, $path) as $status => $codes) {
                foreach ($codes as $code) {
                    $expected = $catalog[$code] ?? $generic[$code] ?? null;

                    expect($expected)->toBe((int) $status, "{$method} {$path}: {$code} con {$status} no figura así en el catálogo.");
                }
            }
        }
    }
});

it('propagates domain errors thrown by actions and the services they call', function () {
    $codes = documented_codes(openapi_spec(), 'patch', '/users/{user}');

    expect($codes['409'])->toContain('LAST_ADMINISTRATOR')
        ->and($codes['422'])->toContain('VALIDATION_FAILED', 'SELF_DEACTIVATION_NOT_ALLOWED', 'VALUE_ALREADY_EXISTS')
        ->and($codes['403'])->toContain('FORBIDDEN', 'PASSWORD_CHANGE_REQUIRED')
        ->and($codes['419'])->toBe(['CSRF_TOKEN_MISMATCH']);
});

it('documents the state machine conflicts thrown inside transactions', function () {
    $spec = openapi_spec();

    expect(documented_codes($spec, 'post', '/locations/{location}/block')['409'])->toBe(['INVALID_STATE_TRANSITION'])
        ->and(documented_codes($spec, 'post', '/locations/{location}/block')['422'])->toContain('WAREHOUSE_INACTIVE')
        ->and(documented_codes($spec, 'patch', '/categories/{category}')['422'])->toContain('CATEGORY_HIERARCHY_CYCLE');
});

it('documents the session requirements of the SPA authentication', function () {
    $spec = openapi_spec();
    $login = $spec['paths']['/auth/login']['post'];
    $me = $spec['paths']['/auth/me']['get'];

    expect($login['security'])->toBe([['xsrfToken' => []]])
        ->and(documented_codes($spec, 'post', '/auth/login'))->toMatchArray([
            '403' => ['USER_INACTIVE'],
            '419' => ['CSRF_TOKEN_MISMATCH'],
            '429' => ['TOO_MANY_REQUESTS'],
        ])
        ->and($me['security'])->toBe([['sessionCookie' => []]])
        // La sesión con cambio pendiente admite /auth/me (REG-021).
        ->and(documented_codes($spec, 'get', '/auth/me'))->not->toHaveKey('403')
        ->and($spec['paths']['/health']['get']['security'])->toEqual([new stdClass]);
});

it('documents the idempotency key and its errors on routes with the idempotency middleware', function () {
    Route::prefix('api/v1')->middleware('api')->group(function () {
        Route::post('/openapi-test/idempotent', fn () => response()->noContent())->middleware(Idempotency::MIDDLEWARE);
    });

    $spec = openapi_spec(fresh: true);
    $operation = $spec['paths']['/openapi-test/idempotent']['post'];
    $header = collect($operation['parameters'])->firstWhere('name', Idempotency::HEADER);

    expect($header)->toMatchArray(['in' => 'header', 'required' => true])
        ->and(documented_codes($spec, 'post', '/openapi-test/idempotent'))->toMatchArray([
            '422' => ['IDEMPOTENCY_KEY_REUSED'],
            '409' => ['IDEMPOTENCY_REQUEST_IN_PROGRESS'],
        ])
        ->and(collect($spec['paths']['/users']['post']['parameters'] ?? [])->firstWhere('name', Idempotency::HEADER))->toBeNull();
});

it('exposes the documentation interface only when enabled and never in production', function (bool $enabled, string $environment, bool $exposed) {
    expect(ApiDocumentationServiceProvider::exposesUi($enabled, $environment))->toBe($exposed);
})->with([
    'habilitada en local' => [true, 'local', true],
    'deshabilitada en local' => [false, 'local', false],
    'habilitada en producción' => [true, 'production', false],
]);

it('serves the documentation outside the wiki path when enabled', function () {
    $this->get('/'.ApiDocumentationServiceProvider::DOCUMENT_PATH)
        ->assertOk()
        ->assertJsonPath('servers.0.url', '/api/v1');

    $this->get('/'.ApiDocumentationServiceProvider::UI_PATH)->assertOk();
});
