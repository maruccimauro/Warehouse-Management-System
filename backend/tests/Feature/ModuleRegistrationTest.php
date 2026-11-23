<?php

namespace Tests\Feature;

use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\Modules\Sample\SampleRecord;
use Tests\Fixtures\Modules\Sample\SampleRecordPolicy;
use Tests\Fixtures\Modules\Sample\SampleServiceProvider;
use Tests\TestCase;

class ModuleRegistrationTest extends TestCase
{
    /**
     * Módulos de dominio definidos en ADR-001.
     */
    private const MODULES = [
        'Audit', 'Catalog', 'Fulfillment', 'Identity', 'Inventory',
        'Procurement', 'Receiving', 'Reporting', 'Stocktaking', 'Warehouse',
    ];

    public function test_modules_directory_matches_the_modules_defined_in_adr_001(): void
    {
        $directories = array_map('basename', File::directories(app_path('Modules')));
        sort($directories);

        $this->assertSame(self::MODULES, $directories);
    }

    public function test_every_module_has_a_registered_module_service_provider(): void
    {
        foreach (self::MODULES as $module) {
            $provider = sprintf('App\Modules\%1$s\%1$sServiceProvider', $module);

            $this->assertTrue(class_exists($provider), "Falta {$provider}.");
            $this->assertTrue(is_subclass_of($provider, ModuleServiceProvider::class), "{$provider} no extiende ModuleServiceProvider.");
            $this->assertNotNull($this->app->getProvider($provider), "{$provider} no está registrado en bootstrap/providers.php.");
        }
    }

    public function test_module_routes_are_registered_under_the_api_v1_prefix(): void
    {
        $this->app->register(SampleServiceProvider::class);
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->getJson('/api/v1/sample/ping')
            ->assertOk()
            ->assertExactJson(['pong' => true]);

        $this->assertContains('api', $this->app['router']->getRoutes()->getByName('sample.ping')->gatherMiddleware());
    }

    public function test_module_migrations_and_policies_are_registered(): void
    {
        $this->app->register(SampleServiceProvider::class);

        $expected = realpath(base_path('tests/Fixtures/Modules/Sample/Database/Migrations'));
        $paths = array_map('realpath', $this->app['migrator']->paths());

        $this->assertContains($expected, $paths);
        $this->assertInstanceOf(SampleRecordPolicy::class, Gate::getPolicyFor(SampleRecord::class));
    }
}
