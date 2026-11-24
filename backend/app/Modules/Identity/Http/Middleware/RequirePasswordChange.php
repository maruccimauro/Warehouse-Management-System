<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Exceptions\PasswordChangeRequired;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con una contraseña inicial o reseteada, la sesión solo sirve para cambiarla
 * (REG-021, D5), como el diálogo de clave inicial de SAP, que no deja entrar
 * al sistema sin cambiarla. Las demás rutas autenticadas responden 403
 * PASSWORD_CHANGE_REQUIRED; GET /auth/me lo informa en
 * password_change_required para que la SPA muestre el cambio.
 */
final class RequirePasswordChange
{
    /** Rutas que admite una sesión con cambio de contraseña pendiente. */
    public const ALLOWED_ROUTES = ['auth.login', 'auth.me', 'auth.logout', 'me.password.update'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User
            && $user->password_change_required
            && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            throw new PasswordChangeRequired;
        }

        return $next($request);
    }
}
