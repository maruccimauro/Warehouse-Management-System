<?php

namespace App\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Violación de una regla de negocio con código estable (ADR-010).
 *
 * Cada excepción concreta define su código y su título, que no cambian entre
 * ocurrencias, y su estado HTTP. El detalle describe la ocurrencia concreta y
 * el contexto agrega datos que el cliente puede usar (por ejemplo, la cantidad
 * disponible). El código forma parte de la API pública: cambiarlo es un cambio
 * incompatible.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $detail = '',
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($detail !== '' ? $detail : $this->title(), 0, $previous);
    }

    /**
     * Código estable en mayúsculas, por ejemplo INSUFFICIENT_STOCK.
     */
    abstract public function errorCode(): string;

    /**
     * Resumen del tipo de problema, igual para todas sus ocurrencias.
     */
    abstract public function title(): string;

    /**
     * Estado HTTP. Las violaciones de reglas de negocio responden 422 salvo
     * que la excepción indique otro (409 para conflictos de estado, por ejemplo).
     */
    public function status(): int
    {
        return 422;
    }

    public function detail(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
