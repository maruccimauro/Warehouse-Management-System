<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\ChangeOwnPassword;
use App\Modules\Identity\Http\Requests\ChangePasswordRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Response;

/**
 * Cambio de la propia contraseña (PUT /me/password, REG-021). Es la única
 * operación, además de consultar el usuario y cerrar sesión, que admite una
 * sesión con contraseña inicial o reseteada.
 */
final class PasswordController
{
    public function update(ChangePasswordRequest $request, ChangeOwnPassword $changeOwnPassword): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $changeOwnPassword(
            $user,
            $request->string('password')->toString(),
            $request->hasSession() ? $request->session()->getId() : null,
        );

        return response()->noContent();
    }
}
