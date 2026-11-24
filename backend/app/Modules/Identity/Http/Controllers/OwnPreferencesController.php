<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\ReplaceOwnFavorites;
use App\Modules\Identity\Actions\ReplaceOwnParameters;
use App\Modules\Identity\Actions\UpdateOwnSettings;
use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Http\Requests\UpdateOwnFavoritesRequest;
use App\Modules\Identity\Http\Requests\UpdateOwnParametersRequest;
use App\Modules\Identity\Http\Requests\UpdateOwnSettingsRequest;
use App\Modules\Identity\Http\Resources\AuthenticatedUserResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserPreferences;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Preferencias del usuario autenticado (E2.21, ADR-016): parámetros de SU3,
 * favoritos del árbol, idioma y mostrar códigos. Cada usuario solo lee y
 * escribe las suyas, por eso no hay Policy: alcanza con la sesión. Los
 * @var documentan el tipo en OpenAPI, que Scramble no infiere de is_string()
 * sobre los valores leídos de la base (ADR-012).
 */
final class OwnPreferencesController
{
    public function __construct(private readonly UserPreferences $preferences) {}

    public function parameters(Request $request): JsonResponse
    {
        return response()->json([
            /** @var array{LGN: string|null} */
            'data' => $this->preferences->parameters($this->user($request)),
        ]);
    }

    public function updateParameters(UpdateOwnParametersRequest $request, ReplaceOwnParameters $replace): JsonResponse
    {
        $user = $this->user($request);
        $replace($user, $request->parameters());

        return response()->json([
            /** @var array{LGN: string|null} */
            'data' => $this->preferences->parameters($user),
        ]);
    }

    public function favorites(Request $request): JsonResponse
    {
        return response()->json([
            /** @var list<string> */
            'data' => $this->preferences->favorites($this->user($request)),
        ]);
    }

    public function updateFavorites(UpdateOwnFavoritesRequest $request, ReplaceOwnFavorites $replace): JsonResponse
    {
        $user = $this->user($request);
        $replace($user, $request->favorites());

        return response()->json([
            /** @var list<string> */
            'data' => $this->preferences->favorites($user),
        ]);
    }

    public function update(
        UpdateOwnSettingsRequest $request,
        UpdateOwnSettings $update,
        WarehouseAccess $warehouseAccess,
    ): AuthenticatedUserResource {
        $user = $update($this->user($request), $request->settings());

        return new AuthenticatedUserResource($user, $warehouseAccess->operableWarehouses($user));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
