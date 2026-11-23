<?php

namespace App\Shared\Exceptions;

use Illuminate\Support\Facades\Lang;
use Throwable;

/**
 * Un valor que debe ser único ya está registrado (ADR-010).
 *
 * La valida primero el Form Request con la regla unique; esta excepción es el
 * respaldo ante una carrera entre dos peticiones que pasan la validación a la
 * vez y la base rechaza la segunda por su restricción única. El contexto
 * indica el campo del formulario afectado, para que el cliente marque el error
 * en ese campo igual que con VALIDATION_FAILED.
 *
 * Una operación por lotes, como la generación masiva de ubicaciones, informa
 * además en values cuáles de los valores del lote ya existen.
 */
final class ValueAlreadyExists extends DomainException
{
    /**
     * @param  list<string>  $values
     */
    public function __construct(public readonly string $field, array $values = [], ?Throwable $previous = null)
    {
        parent::__construct(
            detail: self::text('validation.unique', ['attribute' => self::attributeName($field)]),
            context: $values === [] ? ['field' => $field] : ['field' => $field, 'values' => $values],
            previous: $previous,
        );
    }

    public function errorCode(): string
    {
        return 'VALUE_ALREADY_EXISTS';
    }

    public function title(): string
    {
        return self::text('problems.value_already_exists');
    }

    /**
     * Nombre legible del campo, el mismo que usan los mensajes de validación.
     */
    private static function attributeName(string $field): string
    {
        $key = 'validation.attributes.'.$field;

        return Lang::has($key) ? self::text($key) : str_replace('_', ' ', $field);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
