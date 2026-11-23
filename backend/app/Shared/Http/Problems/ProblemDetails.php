<?php

namespace App\Shared\Http\Problems;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Respuesta de error en formato Problem Details (RFC 9457) con el campo de
 * extensión code, que es el identificador estable que usa el cliente (ADR-010).
 */
final readonly class ProblemDetails
{
    public const CONTENT_TYPE = 'application/problem+json';

    /**
     * Prefijo del type de los errores de dominio. Los errores HTTP genéricos
     * usan about:blank, cuyo title es por definición el texto del estado HTTP.
     */
    public const DOMAIN_TYPE_PREFIX = 'urn:wms:problem:';

    /**
     * Estados que Symfony no incluye en Response::$statusTexts. 419 lo usa
     * Laravel para el token CSRF inválido o expirado.
     */
    private const NON_STANDARD_STATUS_TEXTS = [419 => 'Page Expired'];

    /**
     * @param  array<string, mixed>  $extensions  Miembros adicionales (errors, context, debug).
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public int $status,
        public string $code,
        public string $title,
        public string $type = 'about:blank',
        public ?string $detail = null,
        public ?string $instance = null,
        public array $extensions = [],
        public array $headers = [],
    ) {}

    public static function forHttpStatus(int $status, string $code, ?string $detail = null): self
    {
        return new self(
            status: $status,
            code: $code,
            title: Response::$statusTexts[$status] ?? self::NON_STANDARD_STATUS_TEXTS[$status] ?? 'Error',
            detail: $detail,
        );
    }

    public static function typeForDomainCode(string $code): string
    {
        return self::DOMAIN_TYPE_PREFIX.strtolower(str_replace('_', '-', $code));
    }

    public function withInstance(string $instance): self
    {
        return new self($this->status, $this->code, $this->title, $this->type, $this->detail, $instance, $this->extensions, $this->headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->code, $this->title, $this->type, $this->detail, $this->instance, $this->extensions, [...$this->headers, ...$headers]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'instance' => $this->instance,
            'code' => $this->code,
            ...$this->extensions,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse(
            $this->toArray(),
            $this->status,
            [...$this->headers, 'Content-Type' => self::CONTENT_TYPE],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
