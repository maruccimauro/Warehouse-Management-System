<?php

use App\Shared\Http\RequestContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/*
| Identificador de petición (E3.11, ADR-008): AssignRequestId toma un
| X-Request-Id con formato UUID o genera uno, lo deja en el contexto del log y
| lo devuelve en toda respuesta, también en los errores.
*/

it('generates a uuid v7 when the request has no id', function () {
    $requestId = $this->getJson('/api/v1/health')->headers->get(RequestContext::HEADER);

    expect($requestId)->toBeString()
        ->and(Str::isUuid($requestId))->toBeTrue()
        ->and($requestId[14])->toBe('7')
        ->and(Context::get(RequestContext::REQUEST_ID))->toBe($requestId);
});

it('keeps a valid uuid sent by the client, in lowercase', function () {
    $this->withHeader(RequestContext::HEADER, '0193A4B2-7C1D-7E2F-9A3B-4C5D6E7F8A9B')
        ->getJson('/api/v1/health')
        ->assertHeader(RequestContext::HEADER, '0193a4b2-7c1d-7e2f-9a3b-4c5d6e7f8a9b');
});

it('replaces an id that is not a uuid', function (string $header) {
    $requestId = $this->withHeader(RequestContext::HEADER, $header)
        ->getJson('/api/v1/health')
        ->headers->get(RequestContext::HEADER);

    expect($requestId)->not->toBe($header)
        ->and(Str::isUuid($requestId))->toBeTrue();
})->with([
    'texto' => ['pedido-123'],
    'inyección en el log' => ["0193a4b2-7c1d-7e2f-9a3b-4c5d6e7f8a9b\nfalso"],
    'vacío' => [''],
]);

it('returns the id on an error response', function () {
    $this->getJson('/api/v1/ruta-inexistente')
        ->assertNotFound()
        ->assertHeader(RequestContext::HEADER);
});

it('keeps the ip out of the log context', function () {
    $this->getJson('/api/v1/health');

    expect(Context::has(RequestContext::IP))->toBeFalse()
        ->and(Context::getHidden(RequestContext::IP))->toBe('127.0.0.1');
});
