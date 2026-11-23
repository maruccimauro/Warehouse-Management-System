<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el idioma de la petición: preferencia del usuario (columna
 * users.locale, en E2), luego Accept-Language y, si no coincide ninguno, el
 * primero de app.supported_locales.
 *
 * Es middleware global para que también salgan traducidos los errores que se
 * producen antes de llegar a la ruta: 404 de ruta inexistente, 419 de CSRF y
 * 401 de autenticación.
 */
final readonly class SetLocale
{
    public function __construct(
        private Application $app,
        private Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);
        $this->app->setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Language', $locale);
        // Las cachés intermedias deben distinguir la respuesta según el idioma pedido.
        $response->setVary('Accept-Language', false);

        return $response;
    }

    private function resolve(Request $request): string
    {
        $supported = $this->supportedLocales();

        return $request->getPreferredLanguage($supported) ?? $supported[0];
    }

    /**
     * @return non-empty-list<string>
     */
    private function supportedLocales(): array
    {
        $configured = $this->config->get('app.supported_locales');
        $locales = is_array($configured) ? array_values(array_filter($configured, is_string(...))) : [];

        if ($locales === []) {
            $fallback = $this->config->get('app.locale');

            return [is_string($fallback) ? $fallback : 'es'];
        }

        return $locales;
    }
}
