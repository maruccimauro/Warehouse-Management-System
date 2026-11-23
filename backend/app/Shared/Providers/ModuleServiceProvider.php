<?php

namespace App\Shared\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base de los ServiceProvider de módulo (ADR-001).
 *
 * Cada módulo vive en app/Modules/{Nombre} con su provider en la raíz del
 * directorio. Por convención, el provider registra:
 *
 * - Routes/api.php bajo el prefijo /api/v1 y el grupo de middleware api.
 * - Database/Migrations como ruta de migraciones del módulo.
 * - Las policies declaradas en $policies.
 *
 * Los directorios que el módulo no tiene se ignoran.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public const API_PREFIX = 'api/v1';

    /**
     * Policies del módulo: clase del modelo => clase de la policy.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPolicies();
    }

    /**
     * Directorio del módulo: el que contiene al provider concreto.
     */
    protected function modulePath(string $path = ''): string
    {
        $file = (string) (new ReflectionClass(static::class))->getFileName();

        return dirname($file).($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    private function registerRoutes(): void
    {
        $routes = $this->modulePath('Routes'.DIRECTORY_SEPARATOR.'api.php');

        if ($this->app->routesAreCached() || ! is_file($routes)) {
            return;
        }

        Route::prefix(self::API_PREFIX)
            ->middleware('api')
            ->group($routes);
    }

    private function registerMigrations(): void
    {
        $migrations = $this->modulePath('Database'.DIRECTORY_SEPARATOR.'Migrations');

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    private function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
