<?php

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;

/**
 * Propaga a quien llama las excepciones del código de la aplicación (ADR-012).
 *
 * Scramble solo ve los throw y los @throws del propio método del controlador;
 * no sigue las llamadas. Esta extensión suma a cada método:
 *
 * - En cada llamada a un método de una clase de App (una Action invocable con
 *   $action(...), un servicio con $this->servicio->metodo()), las excepciones
 *   que Scramble conoce de ese método.
 * - En cada llamada que recibe un closure (DB::transaction(fn () => ...), por
 *   ejemplo), las excepciones del closure, porque se asume que se ejecuta.
 *
 * Scramble analiza cada método llamado con las mismas extensiones, así que la
 * propagación es transitiva: LastAdministrator, que lanza
 * AdministratorContinuity dentro de la transacción de UpdateUser, llega al
 * controlador sin repetirla. Lo que Scramble no puede tipar (una llamada sobre
 * mixed) se declara con @throws en el método que la contiene.
 */
final class CalledMethodExceptions implements ExpressionExceptionExtension
{
    private const APPLICATION_NAMESPACE = 'App\\';

    /**
     * @return array<ObjectType|Generic>
     */
    public function getException(Expr $node, Scope $scope): array
    {
        if (! $node instanceof Expr\CallLike || $node->isFirstClassCallable()) {
            return [];
        }

        return self::onlyObjects([
            ...$this->calledMethodExceptions($node, $scope),
            ...$this->closureArgumentExceptions($node, $scope),
        ], $scope);
    }

    /**
     * @return list<Type>
     */
    private function calledMethodExceptions(Expr\CallLike $node, Scope $scope): array
    {
        [$callee, $method] = match (true) {
            $node instanceof Expr\FuncCall && $node->name instanceof Expr => [$scope->getType($node->name), '__invoke'],
            $node instanceof Expr\MethodCall && $node->name instanceof Identifier => [$scope->getType($node->var), $node->name->name],
            default => [null, null],
        };

        if ($callee === null || $method === null) {
            return [];
        }

        $callee = self::resolve($callee, $scope);

        if (! $callee instanceof ObjectType || ! str_starts_with($callee->name, self::APPLICATION_NAMESPACE)) {
            return [];
        }

        return array_values($scope->index->getClass($callee->name)?->getMethodDefinition($method)->type->exceptions ?? []);
    }

    /**
     * @return list<Type>
     */
    private function closureArgumentExceptions(Expr\CallLike $node, Scope $scope): array
    {
        $exceptions = [];

        foreach ($node->getArgs() as $argument) {
            if (! $argument->value instanceof Expr\Closure && ! $argument->value instanceof Expr\ArrowFunction) {
                continue;
            }

            $closure = $scope->getType($argument->value);

            if ($closure instanceof FunctionType) {
                $exceptions = [...$exceptions, ...$closure->exceptions];
            }
        }

        return array_values($exceptions);
    }

    /**
     * Los parámetros tipados llegan como plantilla (TUpdateUser, acotada a la
     * clase) y las propiedades como referencia sin resolver ($this->servicio).
     */
    private static function resolve(Type $type, Scope $scope): Type
    {
        $type = ReferenceTypeResolver::getInstance()->resolve($scope, $type);

        return $type instanceof TemplateType && $type->is !== null ? $type->is : $type;
    }

    /**
     * Un throw new X(...) dentro de un closure queda como referencia sin
     * resolver hasta que alguien la resuelve; acá se resuelve para poder
     * reconocer la clase.
     *
     * @param  list<Type>  $exceptions
     * @return list<ObjectType>
     */
    private static function onlyObjects(array $exceptions, Scope $scope): array
    {
        $resolved = array_map(fn (Type $exception): Type => ReferenceTypeResolver::getInstance()->resolve($scope, $exception), $exceptions);

        return array_values(array_filter($resolved, fn (Type $exception): bool => $exception instanceof ObjectType));
    }
}
