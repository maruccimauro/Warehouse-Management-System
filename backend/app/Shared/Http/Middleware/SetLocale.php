<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Localization\SupportedLocales;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el idioma de la petición: Accept-Language y, si no coincide
 * ninguno, el primero de app.supported_locales. La preferencia del usuario
 * (users.locale) tiene prioridad: la aplica ApplyPreferredLocale del módulo
 * Identity en el grupo api, porque este middleware es global y corre antes de
 * que exista la sesión.
 *
 * Es middleware global para que también salgan traducidos los errores que se
 * producen antes de llegar a la ruta: 404 de ruta inexistente, 419 de CSRF y
 * 401 de autenticación.
 */
final readonly class SetLocale
{
    public function __construct(
        private Application $app,
        private SupportedLocales $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supported = $this->locales->all();
        $this->app->setLocale($request->getPreferredLanguage($supported) ?? $supported[0]);

        /** @var Response $response */
        $response = $next($request);

        // El idioma vigente al terminar: puede ser el preferido del usuario.
        $response->headers->set('Content-Language', $this->app->getLocale());
        // Las cachés intermedias deben distinguir la respuesta según el idioma pedido.
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
