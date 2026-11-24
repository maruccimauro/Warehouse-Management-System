<?php

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reemplaza las respuestas de error que Scramble documenta con el formato de
 * Laravel ({message, errors}) por las que realmente produce
 * ProblemDetailsRenderer (ADR-010): validación, autenticación, autorización,
 * recurso inexistente, CSRF y los errores HTTP. Scramble da prioridad a la
 * última extensión registrada, por eso alcanza con registrarla.
 */
final class HttpProblemToResponse extends HttpExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $this->statusOf($type) !== null;
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type): ?Response
    {
        $status = $this->statusOf($type);

        if ($status === null) {
            return null;
        }

        $response = $type->isInstanceOf(ValidationException::class)
            ? ProblemResponses::validation($this->components)
            : ProblemResponses::http($this->components, $status);

        if ($status === 429) {
            $response->addHeader('Retry-After', new Header(
                description: 'Segundos hasta que se admite una nueva solicitud.',
                required: true,
                schema: ProblemResponses::toSchema(new IntegerType),
            ));
        }

        return $response;
    }

    private function statusOf(Type $type): ?int
    {
        if (! $type instanceof ObjectType) {
            return null;
        }

        return match (true) {
            $type->isInstanceOf(ValidationException::class) => 422,
            $type->isInstanceOf(AuthenticationException::class) => 401,
            $type->isInstanceOf(AuthorizationException::class) => 403,
            $type->isInstanceOf(TokenMismatchException::class) => 419,
            $type->isInstanceOf(RecordsNotFoundException::class),
            $type->isInstanceOf(NotFoundHttpException::class) => 404,
            $type->isInstanceOf(HttpException::class) => $this->httpStatusOf($type),
            default => null,
        };
    }

    private function httpStatusOf(ObjectType $type): ?int
    {
        // Mismo criterio que la extensión de Scramble para leer el estado inferido:
        // el índice 7 es la plantilla TCode cuando el tipo se infirió del código.
        $templates = $type instanceof Generic ? $type->templateTypes : [];
        $codeType = count($templates) > 3 ? ($templates[7] ?? null) : ($templates[0] ?? null);

        return $this->getResponseCode($codeType, $type);
    }
}
