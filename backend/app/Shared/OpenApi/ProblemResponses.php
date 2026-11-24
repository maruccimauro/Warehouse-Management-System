<?php

namespace App\Shared\OpenApi;

use App\Shared\Exceptions\ProblemDetailsRenderer;
use App\Shared\Http\Problems\ProblemDetails;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;

/**
 * Respuestas de error con el formato Problem Details de ADR-010, para la
 * especificación OpenAPI (ADR-012).
 *
 * Cada código es un esquema de components (ProblemLastAdministrator, por
 * ejemplo): el esquema compartido ProblemDetails con el código fijo. Scramble
 * agrupa las respuestas de un mismo estado HTTP en un anyOf, así cada
 * operación muestra los códigos que puede devolver y el cliente, que decide
 * por code, sabe cuáles tratar.
 *
 * Los métodos fluidos de Scramble no declaran tipo de retorno: se llaman como
 * sentencias sobre variables tipadas para que PHPStan siga el tipo.
 */
final class ProblemResponses
{
    public const SCHEMA = 'ProblemDetails';

    /**
     * Respuesta de un error HTTP genérico (type about:blank), con el código de
     * ProblemDetailsRenderer::HTTP_CODES.
     */
    public static function http(Components $components, int $status): Response
    {
        $code = ProblemDetailsRenderer::HTTP_CODES[$status] ?? 'HTTP_ERROR';
        $title = ProblemDetails::forHttpStatus($status, $code)->title;

        return self::make($components, $status, $code, $title, 'about:blank');
    }

    /**
     * 422 VALIDATION_FAILED con los errores por campo, como lo arma
     * ProblemDetailsRenderer a partir de la ValidationException.
     */
    public static function validation(Components $components): Response
    {
        $title = __('problems.validation.title');

        return self::make(
            $components,
            422,
            'VALIDATION_FAILED',
            is_string($title) ? $title : 'problems.validation.title',
            'about:blank',
            ['errors' => self::errorsMember()],
        );
    }

    /**
     * @param  array<string, Type>  $members  Miembros propios del código (errors, context).
     */
    public static function make(
        Components $components,
        int $status,
        string $code,
        string $title,
        string $type,
        array $members = [],
    ): Response {
        $name = self::SCHEMA.Str::studly(strtolower($code));

        if (! $components->hasSchema($name)) {
            $variant = new ObjectType;
            $variant->addProperty('type', self::constant(new StringType, $type));
            $variant->addProperty('status', self::constant(new IntegerType, $status));
            $variant->addProperty('code', self::constant(new StringType, $code));

            foreach ($members as $member => $memberType) {
                $variant->addProperty($member, $memberType);
            }

            $variant->setRequired(['code', ...array_keys($members)]);

            $schema = new AllOf;
            $schema->setItems([self::reference($components), $variant]);
            $schema->setDescription($title);

            $components->addSchema($name, self::toSchema($schema));
        }

        // Cada código es un esquema de components: la operación solo lo referencia.
        $response = Response::make($status);
        $response->setDescription("`{$code}`: {$title}");
        $response->setContent(ProblemDetails::CONTENT_TYPE, self::toSchema(new Reference('schemas', $name, $components)));

        return $response;
    }

    /**
     * Esquema compartido, registrado una sola vez en components.
     */
    public static function reference(Components $components): Reference
    {
        if (! $components->hasSchema(self::SCHEMA)) {
            $components->addSchema(self::SCHEMA, self::toSchema(self::problemDetailsSchema()));
        }

        return new Reference('schemas', self::SCHEMA, $components);
    }

    /**
     * Miembro context de los errores de dominio: datos de la ocurrencia que el
     * cliente puede usar, distintos en cada código.
     */
    public static function contextMember(): Type
    {
        return self::described(new ObjectType, 'Datos de la ocurrencia que el cliente puede usar; su contenido depende del código.');
    }

    /**
     * Miembro errors de VALIDATION_FAILED: mensajes por campo.
     */
    public static function errorsMember(): Type
    {
        $messages = new ArrayType;
        $messages->setItems(new StringType);

        $errors = new ObjectType;
        $errors->additionalProperties($messages);

        return self::described($errors, 'Mensajes de error por campo, con la misma clave que el campo enviado.');
    }

    private static function problemDetailsSchema(): ObjectType
    {
        $detail = new StringType;
        $detail->nullable(true);

        $schema = new ObjectType;
        $schema->addProperty('type', self::described(new StringType, 'urn:wms:problem:{código} en los errores de dominio; about:blank en los errores HTTP genéricos.'));
        $schema->addProperty('title', self::described(new StringType, 'Resumen del tipo de problema, igual en todas sus ocurrencias.'));
        $schema->addProperty('status', self::described(new IntegerType, 'Estado HTTP.'));
        $schema->addProperty('detail', self::described($detail, 'Descripción de la ocurrencia concreta.'));
        $schema->addProperty('instance', self::described(new StringType, 'Ruta de la petición.'));
        $schema->addProperty('code', self::described(new StringType, 'Código estable. El cliente decide por este campo, nunca por title ni por detail.'));
        $schema->setRequired(['type', 'title', 'status', 'code']);
        $schema->setDescription('Error en formato Problem Details (RFC 9457) con el código estable code (ADR-010). Se sirve como application/problem+json.');

        return $schema;
    }

    /**
     * Schema::fromType no declara tipo de retorno.
     */
    public static function toSchema(Type $type): Schema
    {
        /** @var Schema $schema */
        $schema = Schema::fromType($type);

        return $schema;
    }

    /**
     * @template TType of Type
     *
     * @param  TType  $type
     * @return TType
     */
    private static function described(Type $type, string $description): Type
    {
        $type->setDescription($description);

        return $type;
    }

    /**
     * @template TType of Type
     *
     * @param  TType  $type
     * @return TType
     */
    private static function constant(Type $type, string|int $value): Type
    {
        $type->const($value);

        return $type;
    }
}
