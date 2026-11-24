<?php

namespace App\Shared\Http\Listing;

/**
 * Cómo compara un filtro de listado el valor recibido con la columna.
 */
enum FilterMode
{
    /** Igualdad con un único valor: filter[status]=blocked. */
    case Exact;

    /**
     * Fragmento sin distinguir mayúsculas (ILIKE en PostgreSQL), con los
     * comodines del valor escapados: filter[name]=torn busca "%torn%".
     */
    case Partial;

    /**
     * Uno de varios valores, como la selección múltiple de una pantalla de
     * selección de SAP: filter[status][]=active&filter[status][]=blocked.
     */
    case In;

    /**
     * Criterio de pantalla de selección, como un SELECT-OPTIONS de SAP (E2.18):
     * una lista de entradas con signo, operador y límites,
     * filter[code][0][sign]=I&filter[code][0][option]=BT&filter[code][0][low]=A&filter[code][0][high]=M.
     * Las entradas I se combinan con O; las E se restan del resultado. Sin
     * entradas I, el criterio parte de todas las filas.
     */
    case Selection;

    /**
     * Criterio que no es una columna del modelo (una relación, por ejemplo
     * filter[role]=operator en usuarios). Lo aplica el listado en
     * ListRequest::applyCustomFilter(); el nombre sigue siendo de lista blanca.
     */
    case Custom;
}
