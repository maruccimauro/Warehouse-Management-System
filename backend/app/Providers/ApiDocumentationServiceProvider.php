<?php

namespace App\Providers;

use App\Modules\Identity\OpenApi\SpaSessionDocument;
use App\Modules\Identity\OpenApi\SpaSessionOperation;
use App\Shared\OpenApi\ApiDocument;
use App\Shared\OpenApi\CalledMethodExceptions;
use App\Shared\OpenApi\DomainExceptionToResponse;
use App\Shared\OpenApi\HttpProblemToResponse;
use App\Shared\OpenApi\IdempotencyKeyOperation;
use App\Shared\OpenApi\ProhibitedFieldRule;
use Dedoc\Scramble\Configuration\DocumentTransformers;
use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

/**
 * Documentación OpenAPI con Scramble (ADR-012).
 *
 * Scramble es una dependencia de desarrollo: sin el paquete instalado (imagen
 * de producción con --no-dev) este provider no hace nada. Las extensiones se
 * registran acá y no en config/scramble.php porque las de operación tienen que
 * correr antes que las de Scramble, y la configuración solo las agrega al final.
 *
 * La interfaz (/api/docs) y su JSON (/api/docs.json) se registran solo con
 * wms.api_docs.enabled y fuera de producción: en producción no existen (404).
 * La especificación pública es el archivo versionado que genera scramble:export.
 */
final class ApiDocumentationServiceProvider extends ServiceProvider
{
    public const UI_PATH = 'api/docs';

    public const DOCUMENT_PATH = 'api/docs.json';

    public function boot(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::registerExtensions([
            CalledMethodExceptions::class,
            HttpProblemToResponse::class,
            DomainExceptionToResponse::class,
        ]);

        Scramble::configure()
            ->withOperationTransformers(function (OperationTransformers $transformers): void {
                $transformers->prepend([
                    SpaSessionOperation::class,
                    IdempotencyKeyOperation::class,
                ]);
            })
            ->withRuleTransformers(ProhibitedFieldRule::class)
            ->withDocumentTransformers(function (DocumentTransformers $transformers): void {
                $transformers->append([
                    ApiDocument::class,
                    SpaSessionDocument::class,
                ]);
            });

        if (self::exposesUi((bool) config('wms.api_docs.enabled'), $this->app->environment())) {
            Scramble::configure()->expose(ui: self::UI_PATH, document: self::DOCUMENT_PATH);
        } else {
            Scramble::configure()->expose(false);
        }
    }

    /**
     * La variable sola no alcanza: un WMS_API_DOCS_ENABLED olvidado en
     * producción no publica la interfaz ni su JSON.
     */
    public static function exposesUi(bool $enabled, string $environment): bool
    {
        return $enabled && $environment !== 'production';
    }
}
