<?php

namespace App\Shared\OpenApi;

use App\Shared\Exceptions\DomainException;
use App\Shared\Http\Problems\ProblemDetails;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionClass;

/**
 * Documenta cada excepción de dominio que una operación puede lanzar con su
 * estado y su código (ADR-010, ADR-012).
 *
 * Código, título y estado no dependen de la ocurrencia (DomainException), así
 * que se leen de una instancia creada sin constructor. Scramble encuentra las
 * excepciones en el código que infiere desde el controlador; donde no llega,
 * la Action las declara con @throws.
 */
final class DomainExceptionToResponse extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(DomainException::class)
            && class_exists($type->name)
            && ! (new ReflectionClass($type->name))->isAbstract();
    }

    public function toResponse(Type $type): ?Response
    {
        if (! $type instanceof ObjectType || ! is_a($type->name, DomainException::class, true)) {
            return null;
        }

        $exception = (new ReflectionClass($type->name))->newInstanceWithoutConstructor();

        return ProblemResponses::make(
            $this->components,
            $exception->status(),
            $exception->errorCode(),
            $exception->title(),
            ProblemDetails::typeForDomainCode($exception->errorCode()),
            ['context' => ProblemResponses::contextMember()],
        );
    }
}
