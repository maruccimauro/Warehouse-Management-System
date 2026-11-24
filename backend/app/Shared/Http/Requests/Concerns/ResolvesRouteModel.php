<?php

namespace App\Shared\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Modelo de un parámetro de la ruta, ya resuelto por la vinculación explícita
 * que registra el provider del módulo. Si el parámetro no es del tipo
 * esperado, la ruta está mal declarada: es un error de programación, no de la
 * petición.
 *
 * @mixin FormRequest
 */
trait ResolvesRouteModel
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel
     */
    protected function routeModel(string $parameter, string $class): Model
    {
        $model = $this->route($parameter);

        if (! $model instanceof $class) {
            throw new LogicException("El parámetro {$parameter} de la ruta no es un {$class}.");
        }

        return $model;
    }
}
