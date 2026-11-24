<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Favoritos del árbol de transacciones (PUT /me/favorites, E2.21). Lista
 * ordenada completa de códigos distintos. Solo se valida el formato: el
 * registro de transacciones vive en el frontend, que ignora los códigos que
 * ya no existen o que el usuario no puede abrir.
 */
class UpdateOwnFavoritesRequest extends FormRequest
{
    public const MAX_FAVORITES = 50;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'favorites' => ['present', 'array', 'list', 'max:'.self::MAX_FAVORITES],
            'favorites.*' => ['required', 'string', 'distinct', 'regex:/^[A-Z][A-Z0-9_]{1,19}$/'],
        ];
    }

    /**
     * @return list<string>
     */
    public function favorites(): array
    {
        $favorites = $this->validated('favorites');

        return is_array($favorites) ? array_values(array_filter($favorites, is_string(...))) : [];
    }
}
