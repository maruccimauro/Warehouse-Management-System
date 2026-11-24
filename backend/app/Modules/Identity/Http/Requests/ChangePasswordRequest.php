<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Cambio de la propia contraseña (REG-021): exige la actual, como SAP, para
 * que una sesión abierta y desatendida no alcance para cambiarla. La nueva
 * cumple Password::defaults(), se confirma y no repite la actual.
 */
class ChangePasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ];
    }
}
