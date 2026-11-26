<?php

use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\ProblemDetailsRenderer;
use App\Shared\Http\Idempotency\EnsureIdempotency;
use App\Shared\Http\Idempotency\Idempotency;
use App\Shared\Http\Idempotency\PruneIdempotencyKeys;
use App\Shared\Http\Middleware\AssignRequestId;
use App\Shared\Http\Middleware\SetLocale;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: ModuleServiceProvider::API_PREFIX,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum en modo SPA (ADR-009): las peticiones de SANCTUM_STATEFUL_DOMAINS
        // a /api usan sesión por cookie y verificación CSRF.
        $middleware->statefulApi();

        // Identificador de petición (X-Request-Id) para correlacionar la auditoría
        // con los logs (ADR-008): primero, para que lo lleve toda respuesta.
        $middleware->prepend(AssignRequestId::class);

        // Idioma de la respuesta según el usuario y Accept-Language (SetLocale).
        $middleware->append(SetLocale::class);

        // Idempotencia por petición (ADR-007): las rutas que modifican stock la
        // declaran con el alias, después de auth:sanctum.
        $middleware->alias([Idempotency::MIDDLEWARE => EnsureIdempotency::class]);
    })
    ->withCommands([PruneIdempotencyKeys::class])
    ->withExceptions(function (Exceptions $exceptions): void {
        // Contrato de errores de la API: Problem Details con código estable (ADR-010).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(
            fn (Throwable $exception, Request $request): ?JsonResponse => $request->is('api/*') || $request->expectsJson()
                ? app(ProblemDetailsRenderer::class)->render($exception, $request)
                : null,
        );

        // Las violaciones de reglas de negocio son respuestas esperadas, no fallas:
        // tampoco se reportan los errores de base de datos que tienen traducción.
        $exceptions->dontReport(DomainException::class);
        $exceptions->dontReportWhen(
            fn (Throwable $exception): bool => $exception instanceof QueryException
                && app(DatabaseErrorTranslator::class)->translate($exception) !== null,
        );
    })->create();
