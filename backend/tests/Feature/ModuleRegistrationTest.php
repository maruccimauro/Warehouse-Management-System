<?php

use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\Modules\Sample\SampleRecord;
use Tests\Fixtures\Modules\Sample\SampleRecordPolicy;
use Tests\Fixtures\Modules\Sample\SampleServiceProvider;

/** Módulos de dominio definidos en ADR-001. */
const DOMAIN_MODULES = [
    'Audit', 'Catalog', 'Fulfillment', 'Identity', 'Inventory',
    'Procurement', 'Receiving', 'Reporting', 'Stocktaking', 'Warehouse',
];

it('matches the modules directory with the modules defined in ADR-001', function () {
    $directories = array_map('basename', File::directories(app_path('Modules')));
    sort($directories);

    expect($directories)->toBe(DOMAIN_MODULES);
});

it('registers a module service provider for every module', function (string $module) {
    $provider = sprintf('App\Modules\%1$s\%1$sServiceProvider', $module);

    expect(class_exists($provider))->toBeTrue("Falta {$provider}.")
        ->and(is_subclass_of($provider, ModuleServiceProvider::class))->toBeTrue("{$provider} no extiende ModuleServiceProvider.")
        ->and($this->app->getProvider($provider))->not->toBeNull("{$provider} no está registrado en bootstrap/providers.php.");
})->with(DOMAIN_MODULES);

it('registers module routes under the api v1 prefix with the api middleware', function () {
    $this->app->register(SampleServiceProvider::class);
    $this->app['router']->getRoutes()->refreshNameLookups();

    $this->getJson('/api/v1/sample/ping')
        ->assertOk()
        ->assertExactJson(['pong' => true]);

    expect($this->app['router']->getRoutes()->getByName('sample.ping')->gatherMiddleware())->toContain('api');
});

it('registers module migrations and policies', function () {
    $this->app->register(SampleServiceProvider::class);

    $expected = realpath(base_path('tests/Fixtures/Modules/Sample/Database/Migrations'));
    $paths = array_map('realpath', $this->app['migrator']->paths());

    expect($paths)->toContain($expected)
        ->and(Gate::getPolicyFor(SampleRecord::class))->toBeInstanceOf(SampleRecordPolicy::class);
});
