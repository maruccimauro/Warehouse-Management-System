<?php

use App\Shared\Http\Listing\ListRequest;
use Tests\Fixtures\Modules\Sample\SampleRecord;
use Tests\Fixtures\Modules\Sample\SampleServiceProvider;

/*
| Convenciones de listado de la API (E2.10): paginación por página con total,
| filtros y orden con lista blanca de campos, rechazo con VALIDATION_FAILED de
| lo que el listado no declara.
*/

beforeEach(function () {
    $this->app->register(SampleServiceProvider::class);
    $this->app['router']->getRoutes()->refreshNameLookups();

    // El DDL de PostgreSQL es transaccional: la tabla se revierte con la prueba.
    (require base_path('tests/Fixtures/Modules/Sample/Database/Migrations/2026_01_01_000000_create_sample_records_table.php'))->up();
});

/**
 * @param  array<string, mixed>  ...$rows
 */
function sampleRecords(array ...$rows): void
{
    foreach ($rows as $row) {
        SampleRecord::query()->create($row);
    }
}

/**
 * @return list<string>
 */
function listedCodes(string $query = ''): array
{
    $response = test()->getJson('/api/v1/sample/records'.($query !== '' ? '?'.$query : ''))->assertOk();

    return array_column($response->json('data'), 'code');
}

it('paginates with the total, the page count and the default page size', function () {
    sampleRecords(['code' => 'B'], ['code' => 'A'], ['code' => 'C']);

    $this->getJson('/api/v1/sample/records')
        ->assertOk()
        ->assertJsonPath('data.0', ['id' => SampleRecord::query()->where('code', 'A')->value('id'), 'code' => 'A', 'name' => '', 'status' => 'active'])
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.per_page', ListRequest::DEFAULT_PER_PAGE)
        ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['from', 'to', 'path']]);
});

it('jumps to the requested page and keeps the filters in the page links', function () {
    sampleRecords(
        ['code' => 'A', 'status' => 'active'],
        ['code' => 'B', 'status' => 'active'],
        ['code' => 'C', 'status' => 'active'],
        ['code' => 'D', 'status' => 'blocked'],
    );

    $response = $this->getJson('/api/v1/sample/records?filter[status][]=active&per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.current_page', 2);

    expect(array_column($response->json('data'), 'code'))->toBe(['C'])
        ->and(urldecode((string) $response->json('links.first')))->toContain('filter[status][0]=active', 'per_page=2');
});

it('sorts ascending and descending by a declared field, with the primary key as tiebreaker', function () {
    sampleRecords(['code' => 'X2', 'name' => 'Beta'], ['code' => 'X1', 'name' => 'Alfa'], ['code' => 'X3', 'name' => 'Beta']);

    expect(listedCodes('sort=name'))->toBe(['X1', 'X2', 'X3'])
        ->and(listedCodes('sort=-name'))->toBe(['X2', 'X3', 'X1'])
        ->and(listedCodes('sort=-code'))->toBe(['X3', 'X2', 'X1']);
});

it('filters by exact value, by fragment without case and by a list of values', function () {
    sampleRecords(
        ['code' => 'A-01', 'name' => 'Tornillo M6', 'status' => 'active'],
        ['code' => 'A-02', 'name' => 'Tuerca M6', 'status' => 'blocked'],
        ['code' => 'A-03', 'name' => 'TORNILLO M8', 'status' => 'inactive'],
    );

    expect(listedCodes('filter[code]=A-02'))->toBe(['A-02'])
        ->and(listedCodes('filter[name]=tornillo'))->toBe(['A-01', 'A-03'])
        ->and(listedCodes('filter[status][]=active&filter[status][]=inactive'))->toBe(['A-01', 'A-03'])
        ->and(listedCodes('filter[name]=m6&filter[status][]=blocked'))->toBe(['A-02']);
});

it('searches the LIKE wildcards of a fragment literally', function () {
    sampleRecords(
        ['code' => 'P1', 'name' => '100% algodón'],
        ['code' => 'P2', 'name' => '100 algodón'],
        ['code' => 'P3', 'name' => 'caja_chica'],
        ['code' => 'P4', 'name' => 'cajaXchica'],
    );

    expect(listedCodes('filter[name]='.urlencode('100%')))->toBe(['P1'])
        ->and(listedCodes('filter[name]=a_c'))->toBe(['P3']);
});

it('ignores empty criteria, like an empty field of a selection screen', function () {
    sampleRecords(['code' => 'A'], ['code' => 'B']);

    expect(listedCodes('filter[code]=&filter[name]='))->toBe(['A', 'B']);
});

it('validates the value of a typed filter', function () {
    sampleRecords(['code' => 'A', 'is_active' => true], ['code' => 'B', 'is_active' => false]);

    expect(listedCodes('filter[is_active]=0'))->toBe(['B']);

    $this->getJson('/api/v1/sample/records?filter[is_active]=quizas')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors(['filter.is_active'], 'errors');
});

it('rejects with VALIDATION_FAILED what the listing does not declare', function (string $query, string $field) {
    $this->getJson('/api/v1/sample/records?'.$query)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors([$field], 'errors');
})->with([
    'filtro no declarado' => ['filter[password]=x', 'filter'],
    'orden no declarado' => ['sort=status', 'sort'],
    'orden con inyección' => ['sort='.urlencode('code;DROP TABLE sample_records'), 'sort'],
    'página cero' => ['page=0', 'page'],
    'página demasiado grande' => ['per_page='.(ListRequest::MAX_PER_PAGE + 1), 'per_page'],
    'lista en filtro exacto' => ['filter[code][]=A', 'filter.code'],
    'texto en filtro de lista' => ['filter[status]=active', 'filter.status'],
    'demasiados valores en la lista' => [implode('&', array_fill(0, ListRequest::MAX_FILTER_VALUES + 1, 'filter[status][]=a')), 'filter.status'],
    'valor de filtro demasiado largo' => ['filter[name]='.str_repeat('a', ListRequest::MAX_FILTER_LENGTH + 1), 'filter.name'],
]);
