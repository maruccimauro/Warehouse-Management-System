<?php

namespace App\Shared\Database;

use App\Shared\Exceptions\DomainException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * Traduce a excepciones de dominio los errores que garantiza la base (ADR-010).
 *
 * - Violaciones de restricciones (SQLSTATE clase 23): por el nombre de la
 *   restricción, que sigue la convención {tabla}_{columnas}_{tipo}.
 * - Excepciones de triggers: por el mensaje, que es el código de dominio
 *   (RAISE EXCEPTION 'REVERSAL_EXCEEDS_REMAINING').
 *
 * Los módulos registran sus traducciones desde su ServiceProvider. Un error sin
 * traducción es un error de programación: translate() devuelve null y la API
 * responde 500 sin detalles internos.
 */
final class DatabaseErrorTranslator
{
    private const INTEGRITY_CONSTRAINT_CLASS = '23';

    private const CONSTRAINT_NAME_PATTERN = '/constraint "(?<name>[^"]+)"/';

    private const DOMAIN_CODE_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    /** @var array<string, class-string<DomainException>> */
    private array $constraints = [];

    /** @var array<string, class-string<DomainException>> */
    private array $triggerCodes = [];

    /**
     * @param  class-string<DomainException>  $exception
     */
    public function mapConstraint(string $constraint, string $exception): void
    {
        $this->assertNotMapped($this->constraints, $constraint, 'restricción');
        $this->constraints[$constraint] = $exception;
    }

    /**
     * @param  class-string<DomainException>  $exception
     */
    public function mapTriggerCode(string $code, string $exception): void
    {
        if (preg_match(self::DOMAIN_CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException("El código de trigger {$code} no tiene formato de código de dominio.");
        }

        $this->assertNotMapped($this->triggerCodes, $code, 'código de trigger');
        $this->triggerCodes[$code] = $exception;
    }

    public function translate(QueryException $exception): ?DomainException
    {
        $message = $this->primaryMessage($exception);

        if ($this->isIntegrityViolation($exception)
            && preg_match(self::CONSTRAINT_NAME_PATTERN, $message, $matches) === 1
            && isset($this->constraints[$matches['name']])) {
            return new $this->constraints[$matches['name']](previous: $exception);
        }

        if (isset($this->triggerCodes[$message])) {
            return new $this->triggerCodes[$message](previous: $exception);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function mappedConstraints(): array
    {
        return array_keys($this->constraints);
    }

    /**
     * @return list<string>
     */
    public function mappedTriggerCodes(): array
    {
        return array_keys($this->triggerCodes);
    }

    private function isIntegrityViolation(QueryException $exception): bool
    {
        return str_starts_with($this->sqlState($exception), self::INTEGRITY_CONSTRAINT_CLASS);
    }

    private function sqlState(QueryException $exception): string
    {
        $state = $exception->errorInfo[0] ?? $exception->getCode();

        return is_scalar($state) ? (string) $state : '';
    }

    /**
     * Primera línea del mensaje del servidor, sin el prefijo de severidad.
     * PDO entrega por ejemplo "ERROR:  REVERSAL_EXCEEDS_REMAINING\nCONTEXT: ...".
     */
    private function primaryMessage(QueryException $exception): string
    {
        $raw = $exception->errorInfo[2] ?? '';
        $firstLine = strtok(is_string($raw) ? $raw : '', "\n");

        return trim((string) preg_replace('/^[A-Z]+:\s+/', '', $firstLine === false ? '' : $firstLine));
    }

    /**
     * @param  array<string, class-string<DomainException>>  $map
     */
    private function assertNotMapped(array $map, string $key, string $kind): void
    {
        if (isset($map[$key])) {
            throw new InvalidArgumentException("La {$kind} {$key} ya tiene traducción a {$map[$key]}.");
        }
    }
}
