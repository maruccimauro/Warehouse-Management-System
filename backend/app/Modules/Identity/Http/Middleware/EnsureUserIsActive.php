<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una sesión de un usuario desactivado no sirve (REG-020, D2).
 *
 * Al desactivar se borran las sesiones del usuario, pero eso no alcanza: un
 * login simultáneo con la desactivación guarda su sesión después del borrado,
 * y con sesiones en Redis (ADR-011) no se pueden buscar por usuario. Este
 * middleware, en el grupo api, cierra la sesión y responde 401 en la primera
 * petición posterior. El usuario ya está cargado por el guard, así que no
 * suma consultas.
 */
final class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();

        if ($user instanceof User && ! $user->is_active) {
            // Solo esta sesión: logout() además rotaría remember_token en la
            // tabla users, que no se usa (no hay "recordarme").
            $guard->logoutCurrentDevice();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
