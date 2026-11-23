<?php

namespace App\Shared\Database;

use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * Traduce a excepciones de dominio los errores que garantiza la base (ADR-010).
 *
 * - Violaciones de restricciones (SQLSTATE clase 23): por el nombre de la
 *   restricción, que sigue la convención {tabla}_{columnas}_{tipo}.
 * - Excepciones de triggers: por el mensaje, que es el código de dominio
 *   (RAISE EXCEPTION 'REVERSAL_EXCEEDS_REMAINING').
 * - Restricciones únicas sobre datos que ingresa el usuario: a
 *   VALUE_ALREADY_EXISTS con el campo afectado, como respaldo de la regla
 *   unique del Form Request ante una carrera.
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

    private const UNIQUE_CONSTRAINT_SUFFIX = '_unique';

    /** @var array<string, class-string<DomainException>> */
    private array $constraints = [];

    /** @var array<string, string> restricción única => campo */
    private array $uniqueFields = [];

    /** @var array<string, class-string<DomainException>> */
    private array $triggerCodes = [];

    /**
     * @param  class-string<DomainException>  $exception
     */
    public function mapConstraint(string $constraint, string $exception): void
    {
        $this->assertConstraintNotMapped($constraint);
        $this->constraints[$constraint] = $exception;
    }

    /**
     * Traduce la restricción única a VALUE_ALREADY_EXISTS sobre el campo del
     * formulario que la viola. En una restricción compuesta, como
     * (warehouse_id, code), el campo es el que ingresa el usuario.
     */
    public function mapUniqueConstraint(string $constraint, string $field): void
    {
        if (! str_ends_with($constraint, self::UNIQUE_CONSTRAINT_SUFFIX)) {
            throw new InvalidArgumentException("La restricción {$constraint} no sigue la convención {tabla}_{columnas}_unique.");
        }

        $this->assertConstraintNotMapped($constraint);
        $this->uniqueFields[$constraint] = $field;
    }

    /**
     * @param  class-string<DomainException>  $exception
     */
    public function mapTriggerCode(string $code, string $exception): void
    {
        if (preg_match(self::DOMAIN_CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException("El código de trigger {$code} no tiene formato de código de dominio.");
        }

        if (isset($this->triggerCodes[$code])) {
            throw new InvalidArgumentException("El código de trigger {$code} ya tiene traducción a {$this->triggerCodes[$code]}.");
        }

        $this->triggerCodes[$code] = $exception;
    }

    public function translate(QueryException $exception): ?DomainException
    {
        $message = $this->primaryMessage($exception);

        if ($this->isIntegrityViolation($exception)
            && preg_match(self::CONSTRAINT_NAME_PATTERN, $message, $matches) === 1) {
            $constraint = $matches['name'];

            if (isset($this->constraints[$constraint])) {
                return new $this->constraints[$constraint](previous: $exception);
            }

            if (isset($this->uniqueFields[$constraint])) {
                return new ValueAlreadyExists($this->uniqueFields[$constraint], $exception);
            }
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
        return [...array_keys($this->constraints), ...array_keys($this->uniqueFields)];
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

    private function assertConstraintNotMapped(string $constraint): void
    {
        $target = $this->constraints[$constraint] ?? (isset($this->uniqueFields[$constraint]) ? ValueAlreadyExists::class : null);

        if ($target !== null) {
            throw new InvalidArgumentException("La restricción {$constraint} ya tiene traducción a {$target}.");
        }
    }
}
