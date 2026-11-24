<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Exceptions\InvalidCredentials;
use App\Modules\Identity\Exceptions\SessionRequired;
use App\Modules\Identity\Exceptions\UserInactive;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\AuthenticatedUserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticación de la SPA por cookie de sesión (Sanctum en modo SPA, ADR-009).
 * El cliente obtiene antes la cookie CSRF en GET /sanctum/csrf-cookie.
 */
class AuthController
{
    public function __construct(private readonly WarehouseAccess $warehouseAccess) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! $request->hasSession()) {
            throw new SessionRequired;
        }

        // El estado de la cuenta se evalúa solo con la contraseña ya verificada
        // (REG-020, D1): un usuario inactivo con la contraseña correcta recibe
        // USER_INACTIVE; cualquier otro fallo, INVALID_CREDENTIALS, sin revelar
        // si la cuenta existe o está desactivada. attemptWhen conserva el
        // timebox del guard contra la medición de tiempos.
        $inactive = false;
        $authenticated = Auth::guard('web')->attemptWhen(
            $request->credentials(),
            function (User $user) use (&$inactive): bool {
                $inactive = ! $user->is_active;

                return ! $inactive;
            },
        );

        if ($inactive) {
            throw new UserInactive;
        }

        if (! $authenticated) {
            throw new InvalidCredentials;
        }

        // Nuevo identificador de sesión al autenticar: evita la fijación de sesión.
        $request->session()->regenerate();

        return $this->userResource($this->authenticatedUser($request))->response();
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): AuthenticatedUserResource
    {
        return $this->userResource($this->authenticatedUser($request));
    }

    private function userResource(User $user): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($user, $this->warehouseAccess->operableWarehouses($user));
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
