<?php

namespace App\Shared\OpenApi;

use App\Shared\Exceptions\IdempotencyKeyReused;
use App\Shared\Exceptions\IdempotencyRequestInProgress;
use App\Shared\Http\Idempotency\Idempotency;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;

/**
 * Documenta la cabecera Idempotency-Key y sus errores en las rutas con el
 * middleware de idempotencia (ADR-007, contrato en Idempotency).
 *
 * Se antepone a las extensiones de Scramble para que los errores se agrupen
 * por estado junto con los que infiere del controlador.
 */
final class IdempotencyKeyOperation extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! self::isIdempotent($routeInfo)) {
            return;
        }

        $header = new Parameter(Idempotency::HEADER, 'header');
        $header->required(true);
        $header->setSchema(ProblemResponses::toSchema(new StringType));
        $header->description('Clave que el cliente genera al iniciar el intento de operación y repite en sus reintentos. Misma clave y mismo cuerpo: se devuelve la respuesta almacenada; misma clave con otro cuerpo: 422 IDEMPOTENCY_KEY_REUSED.');

        $operation->addParameters([$header]);

        $actionType = $routeInfo->getActionType();

        if ($actionType !== null) {
            $actionType->exceptions = [
                ...$actionType->exceptions,
                new ObjectType(IdempotencyKeyReused::class),
                new ObjectType(IdempotencyRequestInProgress::class),
            ];
        }
    }

    private static function isIdempotent(RouteInfo $routeInfo): bool
    {
        foreach ($routeInfo->route->gatherMiddleware() as $middleware) {
            if ($middleware === Idempotency::MIDDLEWARE
                || (is_string($middleware) && str_starts_with($middleware, Idempotency::MIDDLEWARE.':'))) {
                return true;
            }
        }

        return false;
    }
}
