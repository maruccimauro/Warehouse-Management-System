<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\CreateUser;
use App\Modules\Identity\Actions\UpdateUser;
use App\Modules\Identity\Data\UserChanges;
use App\Modules\Identity\Http\Requests\StoreUserRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Http\Requests\UserListRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Usuarios (SU01, ZW91 a ZW93 y SUIM): consulta con users.view; alta, edición,
 * activación, roles, depósitos y reseteo de contraseña con users.manage. No
 * se borran: los documentos los referencian.
 */
final class UserController
{
    private const ATTRIBUTES = ['name', 'email', 'locale'];

    public function index(UserListRequest $request): AnonymousResourceCollection
    {
        return UserResource::collection($request->paginate(User::query()->with(['roles', 'warehouses'])));
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): JsonResponse
    {
        /** @var array{username: string, name: string, email: string, password: string, locale?: string} $attributes */
        $attributes = $request->safe()->only(['username', ...self::ATTRIBUTES, 'password']);

        $user = $createUser($attributes, $request->roles() ?? [], $request->warehouseIds() ?? []);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load(['roles', 'warehouses']));
    }

    public function update(UpdateUserRequest $request, UpdateUser $updateUser): UserResource
    {
        /** @var array{name?: string, email?: string, locale?: string} $attributes */
        $attributes = $request->safe()->only(self::ATTRIBUTES);
        $password = $request->safe()->only(['password'])['password'] ?? null;

        $changes = new UserChanges(
            attributes: $attributes,
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
            roles: $request->roles(),
            warehouseIds: $request->warehouseIds(),
            password: is_string($password) ? $password : null,
        );

        return new UserResource($updateUser(
            self::actor($request),
            $request->targetUser(),
            $changes,
            $request->hasSession() ? $request->session()->getId() : null,
        ));
    }

    private static function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return $actor;
    }
}
