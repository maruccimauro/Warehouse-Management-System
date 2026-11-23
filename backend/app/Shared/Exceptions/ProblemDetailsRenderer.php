<?php

namespace App\Shared\Exceptions;

use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Http\Problems\ProblemDetails;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Punto único de traducción de excepciones a Problem Details (ADR-010).
 *
 * Recibe la excepción después de que Laravel la normaliza (ModelNotFound pasa
 * a 404, AuthorizationException a 403, TokenMismatch a 419), de modo que solo
 * distingue dominio, validación, autenticación, errores HTTP, base de datos y
 * el resto, que responde 500 sin detalles internos.
 */
final readonly class ProblemDetailsRenderer
{
    /**
     * Códigos de los errores HTTP genéricos. Los de dominio los define cada
     * excepción (catálogo en business-rules.md).
     */
    private const HTTP_CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        413 => 'PAYLOAD_TOO_LARGE',
        415 => 'UNSUPPORTED_MEDIA_TYPE',
        419 => 'CSRF_TOKEN_MISMATCH',
        429 => 'TOO_MANY_REQUESTS',
        503 => 'SERVICE_UNAVAILABLE',
    ];

    /**
     * Detalles genéricos: el mensaje de la excepción puede exponer nombres de
     * clases o rutas internas (ModelNotFoundException, por ejemplo).
     */
    private const HTTP_DETAILS = [
        401 => 'Se requiere una sesión iniciada.',
        404 => 'El recurso solicitado no existe.',
        405 => 'El método HTTP no está permitido para este recurso.',
        419 => 'El token CSRF no es válido o expiró. Obtené uno nuevo en /sanctum/csrf-cookie.',
        429 => 'Se superó el límite de solicitudes. Reintentá más tarde.',
    ];

    public function __construct(
        private DatabaseErrorTranslator $databaseErrors,
        private bool $debug,
    ) {}

    public function render(Throwable $exception, Request $request): JsonResponse
    {
        return $this->toProblem($exception)
            ->withInstance('/'.ltrim($request->path(), '/'))
            ->toResponse();
    }

    public function toProblem(Throwable $exception): ProblemDetails
    {
        if ($exception instanceof QueryException) {
            $exception = $this->databaseErrors->translate($exception) ?? $exception;
        }

        return match (true) {
            $exception instanceof DomainException => $this->fromDomain($exception),
            $exception instanceof ValidationException => $this->fromValidation($exception),
            $exception instanceof AuthenticationException => ProblemDetails::forHttpStatus(401, self::HTTP_CODES[401], self::HTTP_DETAILS[401]),
            $exception instanceof HttpExceptionInterface => $this->fromHttp($exception),
            default => $this->internalError($exception),
        };
    }

    private function fromDomain(DomainException $exception): ProblemDetails
    {
        return new ProblemDetails(
            status: $exception->status(),
            code: $exception->errorCode(),
            title: $exception->title(),
            type: ProblemDetails::typeForDomainCode($exception->errorCode()),
            detail: $exception->detail(),
            extensions: $exception->context() === [] ? [] : ['context' => $exception->context()],
        );
    }

    private function fromValidation(ValidationException $exception): ProblemDetails
    {
        return new ProblemDetails(
            status: 422,
            code: 'VALIDATION_FAILED',
            title: 'Los datos enviados no son válidos.',
            detail: 'Uno o más campos tienen errores.',
            extensions: ['errors' => $exception->errors()],
        );
    }

    private function fromHttp(HttpExceptionInterface $exception): ProblemDetails
    {
        $status = $exception->getStatusCode();

        // El mensaje de 403 lo escriben las policies para el usuario; el resto se reemplaza.
        $detail = $status === 403
            ? ($exception->getMessage() !== '' ? $exception->getMessage() : null)
            : (self::HTTP_DETAILS[$status] ?? null);

        $headers = [];
        foreach ($exception->getHeaders() as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $headers[$name] = (string) $value;
            }
        }

        return ProblemDetails::forHttpStatus($status, self::HTTP_CODES[$status] ?? 'HTTP_ERROR', $detail)
            ->withHeaders($headers);
    }

    private function internalError(Throwable $exception): ProblemDetails
    {
        $problem = ProblemDetails::forHttpStatus(500, 'INTERNAL_ERROR', 'Ocurrió un error inesperado.');

        if (! $this->debug) {
            return $problem;
        }

        return new ProblemDetails(
            status: $problem->status,
            code: $problem->code,
            title: $problem->title,
            detail: $problem->detail,
            extensions: ['debug' => [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]],
        );
    }
}
