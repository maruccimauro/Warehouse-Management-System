<?php

namespace App\Shared\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Códigos y coordenadas en mayúsculas y sin espacios en los extremos antes de
 * validar, para que la regla unique compare contra lo que se va a guardar
 * (los modelos también normalizan, y la base lo exige con *_code_uppercase_check).
 *
 * @mixin FormRequest
 */
trait NormalizesCodes
{
    /**
     * Letras, dígitos y separadores internos guion o guion bajo: A-01-01.
     */
    public const CODE_PATTERN = '/^[A-Z0-9]+([_-][A-Z0-9]+)*$/';

    /**
     * @param  list<string>  $fields
     */
    protected function normalizeCodes(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = mb_strtoupper(trim($value));
            }
        }

        $this->merge($normalized);
    }
}
