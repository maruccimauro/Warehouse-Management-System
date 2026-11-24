<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Shared\Localization\SupportedLocales;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idioma preferido del usuario (users.locale, E2.21) por encima de
 * Accept-Language. SetLocale es global y corre antes de que exista la sesión,
 * así que resuelve solo con la cabecera; este middleware, en el grupo api
 * después del de Sanctum que inicia la sesión, aplica la preferencia antes de
 * validar la petición, y los mensajes de validación y de dominio salen en el
 * idioma del usuario. Un idioma que ya no está en app.supported_locales se
 * ignora. El usuario ya está cargado por el guard: no suma consultas.
 */
final readonly class ApplyPreferredLocale
{
    public function __construct(
        private Application $app,
        private SupportedLocales $locales,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && $this->locales->supports($user->locale)) {
            $this->app->setLocale($user->locale);
        }

        return $next($request);
    }
}
