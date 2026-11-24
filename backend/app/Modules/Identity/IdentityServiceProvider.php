<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Console\SyncAuthorizationCommand;
use App\Modules\Identity\Http\Middleware\ApplyPreferredLocale;
use App\Modules\Identity\Http\Middleware\EnsureUserIsActive;
use App\Modules\Identity\Http\Middleware\RequirePasswordChange;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
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

    /** Intentos de cambio de la propia contraseña por usuario, por minuto. */
    public const PASSWORD_CHANGE_ATTEMPTS = 5;

    /** Largo mínimo de una contraseña (Password::defaults()). */
    public const PASSWORD_MIN_LENGTH = 12;

    protected array $policies = [
        User::class => UserPolicy::class,
    ];

    protected array $uniqueConstraintFields = [
        'users_email_unique' => 'email',
    ];

    public function boot(): void
    {
        parent::boot();

        $this->disableTokenAuthentication();
        $this->registerLoginRateLimiter();
        $this->registerPasswordChangeRateLimiter();
        $this->registerMorphAliases();
        $this->registerAccountStateMiddleware();

        Route::model('user', User::class);

        Password::defaults(fn (): Password => Password::min(self::PASSWORD_MIN_LENGTH)->max(255));

        if ($this->app->runningInConsole()) {
            $this->commands([SyncAuthorizationCommand::class]);
        }
    }

    /**
     * Las columnas polimórficas guardan alias, no nombres de clase
     * (database-design.md). El morph map es obligatorio para todo el sistema:
     * cada módulo registra los alias de sus modelos.
     */
    private function registerMorphAliases(): void
    {
        Relation::enforceMorphMap(['user' => User::class]);
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

    /**
     * Contra la adivinación de la contraseña actual desde una sesión abierta y
     * desatendida.
     */
    private function registerPasswordChangeRateLimiter(): void
    {
        RateLimiter::for('password-change', function (Request $request): Limit {
            $user = $request->user();
            $key = $user instanceof User ? (string) $user->id : (string) $request->ip();

            return Limit::perMinute(self::PASSWORD_CHANGE_ATTEMPTS)->by('password-change:'.$key);
        });
    }

    /**
     * Idioma preferido del usuario (ApplyPreferredLocale, E2.21) y estado de la
     * cuenta en cada petición de la API (REG-020, REG-021): una
     * sesión de un usuario desactivado se cierra con 401, y una con contraseña
     * inicial o reseteada solo admite cambiarla. Van en el grupo api, después
     * del middleware de Sanctum que inicia la sesión, para no depender de que
     * cada módulo los declare en sus rutas.
     */
    private function registerAccountStateMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('api', ApplyPreferredLocale::class);
        $router->pushMiddlewareToGroup('api', EnsureUserIsActive::class);
        $router->pushMiddlewareToGroup('api', RequirePasswordChange::class);
    }
}
