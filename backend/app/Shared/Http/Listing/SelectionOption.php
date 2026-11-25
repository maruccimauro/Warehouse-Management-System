<?php

namespace App\Shared\Http\Listing;

/**
 * Operador de una entrada de FilterMode::Selection, con los códigos de OPTION
 * de un SELECT-OPTIONS de SAP. CP y NP comparan con un patrón en el que *
 * reemplaza cualquier cantidad de caracteres (E2.12, D3); el resto del valor
 * se busca literal y sin distinguir mayúsculas. + y # de SAP no se admiten.
 */
enum SelectionOption: string
{
    case Equal = 'EQ';
    case NotEqual = 'NE';
    case GreaterThan = 'GT';
    case GreaterOrEqual = 'GE';
    case LessThan = 'LT';
    case LessOrEqual = 'LE';
    case Between = 'BT';
    case NotBetween = 'NB';
    case ContainsPattern = 'CP';
    case NotContainsPattern = 'NP';

    /** BT y NB llevan límite superior (HIGH); el resto, solo LOW. */
    public function hasHigh(): bool
    {
        return $this === self::Between || $this === self::NotBetween;
    }

    /** CP y NP comparan texto con un patrón: no se ofrecen en un criterio numérico. */
    public function isPattern(): bool
    {
        return $this === self::ContainsPattern || $this === self::NotContainsPattern;
    }
}
