<?php

namespace App\Providers;

use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Exceptions\ProblemDetailsRenderer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Por nombre: el código de la aplicación no importa clases de tests/. */
    private const CONCURRENCY_SCENARIO_COMMAND = 'Tests\\Support\\Concurrency\\ConcurrencyScenarioCommand';

    public function register(): void
    {
        // Una sola instancia: los módulos registran sus traducciones en boot().
        $this->app->singleton(DatabaseErrorTranslator::class);

        $this->app->singleton(ProblemDetailsRenderer::class, fn (Application $app): ProblemDetailsRenderer => new ProblemDetailsRenderer(
            $app->make(DatabaseErrorTranslator::class),
            (bool) $app->make('config')->get('app.debug'),
        ));
    }

    public function boot(): void
    {
        // TIMESTAMPTZ sin precisión explícita (microsegundos), como el DDL de
        // database-design.md. Laravel usa precisión 0 por defecto.
        Schema::defaultTimePrecision(null);

        // Proceso hijo de las pruebas de concurrencia (E3.14): solo en testing
        // y solo si el código de pruebas está instalado (autoload-dev).
        if ($this->app->environment('testing') && $this->app->runningInConsole() && class_exists(self::CONCURRENCY_SCENARIO_COMMAND)) {
            $this->commands([self::CONCURRENCY_SCENARIO_COMMAND]);
        }
    }
}
