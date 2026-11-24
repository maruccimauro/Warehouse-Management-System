<?php

namespace App\Shared\Http\Listing;

/**
 * Operador de una entrada de FilterMode::Selection, con los códigos de OPTION
 * de un SELECT-OPTIONS de SAP. CP y NP (patrón con *) no se admiten: los
 * comodines quedaron fuera también en la ayuda de búsqueda (E2.17).
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

    /** BT y NB llevan límite superior (HIGH); el resto, solo LOW. */
    public function hasHigh(): bool
    {
        return $this === self::Between || $this === self::NotBetween;
    }
}
