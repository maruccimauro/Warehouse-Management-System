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
     * Criterio que no es una columna del modelo (una relación, por ejemplo
     * filter[role]=operator en usuarios). Lo aplica el listado en
     * ListRequest::applyCustomFilter(); el nombre sigue siendo de lista blanca.
     */
    case Custom;
}
