<?php

namespace App\Providers;

use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Exceptions\ProblemDetailsRenderer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
    }
}
