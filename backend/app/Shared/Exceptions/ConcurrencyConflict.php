<?php

namespace App\Shared\Exceptions;

use Throwable;

/**
 * La operación chocó con otra simultánea (deadlock o falla de serialización)
 * en todos sus intentos (ADR-004). Es 503: el problema es transitorio y el
 * cliente puede reintentar con la misma clave de idempotencia (ADR-007).
 * ProblemDetailsRenderer la arma a partir del error de la base.
 */
final class ConcurrencyConflict extends DomainException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(detail: self::text('problems.concurrency_conflict.detail'), previous: $previous);
    }

    public function errorCode(): string
    {
        return 'CONCURRENCY_CONFLICT';
    }

    public function title(): string
    {
        return self::text('problems.concurrency_conflict.title');
    }

    public function status(): int
    {
        return 503;
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
