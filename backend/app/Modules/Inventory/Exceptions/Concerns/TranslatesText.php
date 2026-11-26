<?php

namespace App\Modules\Inventory\Exceptions\Concerns;

/**
 * Texto traducido de una excepción del módulo, en el idioma de la petición.
 */
trait TranslatesText
{
    /**
     * @param  array<string, string|int>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $text = __($key, array_map(strval(...), $replace));

        return is_string($text) ? $text : $key;
    }
}
