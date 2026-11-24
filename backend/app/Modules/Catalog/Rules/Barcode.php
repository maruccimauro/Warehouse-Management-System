<?php

namespace App\Modules\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Código de barras de un producto: caracteres ASCII imprimibles sin espacios,
 * para admitir códigos internos Code 128. Si es solo numérico y tiene la
 * longitud de un GTIN (EAN-8, UPC-A, EAN-13 o GTIN-14), se exige además el
 * dígito verificador, como las categorías EAN de SAP: detecta un error de
 * tipeo antes de que el código llegue a una etiqueta.
 *
 * No se pasa a mayúsculas: Code 128 distingue mayúsculas y minúsculas, y la
 * comparación al escanear es exacta.
 */
final class Barcode implements ValidationRule
{
    public const MAX_LENGTH = 64;

    private const GTIN_LENGTHS = [8, 12, 13, 14];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[\x21-\x7E]{1,'.self::MAX_LENGTH.'}$/', $value) !== 1) {
            $fail('catalog.barcode_invalid')->translate();

            return;
        }

        if (ctype_digit($value) && in_array(strlen($value), self::GTIN_LENGTHS, true) && ! self::hasValidCheckDigit($value)) {
            $fail('catalog.barcode_check_digit')->translate();
        }
    }

    /**
     * Dígito verificador GS1 (módulo 10): desde la derecha, sin contar el
     * dígito verificador, los dígitos se ponderan alternando 3 y 1.
     */
    public static function hasValidCheckDigit(string $gtin): bool
    {
        $digits = array_map(intval(...), str_split(strrev(substr($gtin, 0, -1))));
        $sum = 0;

        foreach ($digits as $position => $digit) {
            $sum += $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) substr($gtin, -1);
    }
}
