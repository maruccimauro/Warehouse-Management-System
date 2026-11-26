<?php

namespace App\Shared\Numbering;

/**
 * Número interno de un maestro o de un documento (proveedor, operación de
 * inventario, orden de compra), como los rangos de números de SAP: se guarda
 * como entero y se muestra con diez dígitos y ceros a la izquierda, al estilo
 * de la conversión ALPHA. Así un rango BT compara números y el usuario puede
 * escribir 12, 0000000012 o el número completo.
 */
final class InternalNumber
{
    public const DISPLAY_LENGTH = 10;

    /** Hasta 18 dígitos significativos: el máximo que cabe sin riesgo en BIGINT. */
    private const INPUT_PATTERN = '/^\d{1,18}$/';

    /**
     * Ceros a la izquierda hasta diez dígitos (0000000012); un número más
     * largo se muestra completo, sin truncarse.
     */
    public static function format(int $number): string
    {
        return str_pad((string) $number, self::DISPLAY_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Número escrito por el usuario, con o sin ceros a la izquierda, a entero;
     * null si no es un número positivo.
     */
    public static function parse(string $value): ?int
    {
        $digits = ltrim(trim($value), '0');

        return preg_match(self::INPUT_PATTERN, $digits) === 1 ? (int) $digits : null;
    }
}
