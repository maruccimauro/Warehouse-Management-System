<?php

namespace App\Modules\Identity\OpenApi;

use App\Modules\Identity\Exceptions\PasswordChangeRequired;
use App\Modules\Identity\Http\Middleware\RequirePasswordChange;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;

/**
 * Lo que la autenticación de la SPA (ADR-009) agrega a cada operación y que
 * Scramble no ve, porque ocurre en middlewares:
 *
 * - Seguridad: cookie de sesión en las rutas con auth:sanctum y, en los
 *   métodos que modifican, la cabecera X-XSRF-TOKEN.
 * - 419 CSRF_TOKEN_MISMATCH en los métodos que modifican, también en el login.
 * - 403 PASSWORD_CHANGE_REQUIRED en las rutas autenticadas que
 *   RequirePasswordChange no admite con el cambio pendiente (REG-021).
 *
 * Se antepone a las extensiones de Scramble: agrega las excepciones al tipo de
 * la acción y Scramble las agrupa por estado junto con las que infiere.
 */
final class SpaSessionOperation extends OperationExtension
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $authenticated = $this->isAuthenticated($routeInfo);
        $unsafe = in_array(strtoupper($routeInfo->method), self::UNSAFE_METHODS, true);

        $operation->addSecurity(new SecurityRequirement(array_merge(
            $authenticated ? [SpaSessionDocument::SESSION_SCHEME => []] : [],
            $unsafe ? [SpaSessionDocument::XSRF_SCHEME => []] : [],
        )));

        $exceptions = [];

        if ($unsafe) {
            $exceptions[] = new ObjectType(TokenMismatchException::class);
        }

        if ($authenticated && ! in_array($routeInfo->route->getName(), RequirePasswordChange::ALLOWED_ROUTES, true)) {
            $exceptions[] = new ObjectType(PasswordChangeRequired::class);
        }

        $actionType = $routeInfo->getActionType();

        if ($actionType !== null && $exceptions !== []) {
            $actionType->exceptions = [...$actionType->exceptions, ...$exceptions];
        }
    }

    private function isAuthenticated(RouteInfo $routeInfo): bool
    {
        foreach ($routeInfo->route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'auth' || Str::startsWith($middleware, 'auth:'))) {
                return true;
            }
        }

        return false;
    }
}
