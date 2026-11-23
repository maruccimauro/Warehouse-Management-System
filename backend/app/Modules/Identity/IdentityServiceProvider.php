<?php

namespace App\Modules\Identity;

use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

/**
 * Módulo Identity: usuarios, autenticación, roles y permisos, asignación de depósitos.
 */
class IdentityServiceProvider extends ModuleServiceProvider
{
    /** Intentos de login por email y dirección IP, por minuto. */
    public const LOGIN_ATTEMPTS_PER_ACCOUNT = 5;

    /** Intentos de login por dirección IP, por minuto, para cualquier email. */
    public const LOGIN_ATTEMPTS_PER_IP = 20;

    public function boot(): void
    {
        parent::boot();

        $this->disableTokenAuthentication();
        $this->registerLoginRateLimiter();
    }

    /**
     * La API autentica solo por cookie de sesión (ADR-009). Sin esto, un
     * encabezado Authorization: Bearer haría que Sanctum busque la tabla de
     * tokens personales, que no existe.
     */
    private function disableTokenAuthentication(): void
    {
        Sanctum::getAccessTokenFromRequestUsing(fn (): ?string => null);
    }

    /**
     * Dos límites: por cuenta e IP, contra la fuerza bruta sobre un usuario, y
     * por IP, contra el recorrido de muchos emails desde un mismo origen.
     */
    private function registerLoginRateLimiter(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = mb_strtolower($request->string('email')->toString());
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_ACCOUNT)->by('login:account:'.$email.'|'.$ip),
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_IP)->by('login:ip:'.$ip),
            ];
        });
    }
}
