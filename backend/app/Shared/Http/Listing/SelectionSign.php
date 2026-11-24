<?php

namespace App\Shared\Http\Listing;

/**
 * Signo de una entrada de FilterMode::Selection, como SIGN en SAP: I incluye
 * las filas que cumplen la entrada y E las excluye.
 */
enum SelectionSign: string
{
    case Include = 'I';
    case Exclude = 'E';
}
