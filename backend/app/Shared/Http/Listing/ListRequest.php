<?php

namespace App\Shared\Http\Listing;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Petición de listado con paginación por página, filtros y orden con lista
 * blanca de campos (E2.10).
 *
 * Cada listado declara qué campos admite filtrar y ordenar, como los criterios
 * de una pantalla de selección de SAP. Lo que no está declarado se rechaza con
 * 422 VALIDATION_FAILED (ADR-010) en lugar de ignorarse en silencio, y la
 * columna nunca sale de la petición: solo de la lista declarada. Las reglas son
 * de Form Request para que la documentación OpenAPI las tome sin anotaciones
 * (ADR-012).
 *
 * Parámetros: filter[campo]=valor, sort=campo o sort=-campo (descendente),
 * page y per_page. La respuesta usa el paginador de Laravel con total, porque
 * la grilla de datos maestros muestra el total y salta de página; el libro de
 * movimientos de E3 usará cursor.
 */
abstract class ListRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public const MAX_PER_PAGE = 200;

    public const MAX_FILTER_LENGTH = 100;

    public const MAX_FILTER_VALUES = 50;

    /**
     * Campos filtrables: nombre de la columna del modelo => modo.
     *
     * @return array<string, FilterMode>
     */
    abstract protected function filters(): array;

    /**
     * Columnas del modelo por las que se puede ordenar.
     *
     * @return list<string>
     */
    abstract protected function sorts(): array;

    /**
     * Orden cuando la petición no indica uno, con la misma sintaxis que sort.
     */
    abstract protected function defaultSort(): string;

    /**
     * Reglas del valor de los filtros que no son texto, por campo. Por ejemplo,
     * 'zone_id' => ['integer'] o 'is_active' => ['boolean'], para que un valor
     * inválido sea 422 y no un error de conversión de la base. Sin regla
     * propia, el valor es texto de hasta MAX_FILTER_LENGTH caracteres.
     *
     * @return array<string, list<mixed>>
     */
    protected function filterValueRules(): array
    {
        return [];
    }

    /**
     * Un listado dentro de un recurso con alcance (las ubicaciones de un
     * depósito, por ejemplo) redefine este método con la Policy del recurso:
     * Laravel autoriza antes de validar, y así un usuario sin alcance recibe
     * 404 y no un 422 que revele que el recurso existe (REG-005). Un listado
     * general filtra con el scope visibleTo en el controlador.
     */
    public function authorize(): bool|Response
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'filter' => ['sometimes', 'array:'.implode(',', array_keys($this->filters()))],
            'sort' => ['sometimes', 'string', Rule::in($this->sortValues())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];

        $valueRules = $this->filterValueRules();

        foreach ($this->filters() as $field => $mode) {
            $value = $valueRules[$field] ?? ['string', 'max:'.self::MAX_FILTER_LENGTH];

            if ($mode === FilterMode::In) {
                $rules["filter.{$field}"] = ['sometimes', 'nullable', 'list', 'max:'.self::MAX_FILTER_VALUES];
                $rules["filter.{$field}.*"] = ['required', ...$value];
            } else {
                $rules["filter.{$field}"] = ['sometimes', 'nullable', ...$value];
            }
        }

        return $rules;
    }

    /**
     * Aplica los filtros y el orden validados. El orden agrega la clave
     * primaria como desempate, para que dos páginas no repitan ni salteen filas
     * con el mismo valor en la columna ordenada.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        $this->applyFilters($query);

        $sort = $this->validated('sort', $this->defaultSort());
        $sort = is_string($sort) ? $sort : $this->defaultSort();
        $column = ltrim($sort, '-');

        $query->orderBy($query->qualifyColumn($column), str_starts_with($sort, '-') ? 'desc' : 'asc');

        $key = $query->getModel()->getKeyName();
        if ($column !== $key) {
            $query->orderBy($query->qualifyColumn($key));
        }

        return $query;
    }

    /**
     * Filtra, ordena y pagina. Los enlaces de página conservan los parámetros
     * de la petición.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query): LengthAwarePaginator
    {
        $perPage = $this->validated('per_page', self::DEFAULT_PER_PAGE);

        return $this->apply($query)
            ->paginate(is_numeric($perPage) ? (int) $perPage : self::DEFAULT_PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return list<string>
     */
    private function sortValues(): array
    {
        $values = [];

        foreach ($this->sorts() as $column) {
            $values[] = $column;
            $values[] = '-'.$column;
        }

        return $values;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyFilters(Builder $query): void
    {
        $values = $this->validated('filter', []);
        $values = is_array($values) ? $values : [];

        foreach ($this->filters() as $field => $mode) {
            $value = $values[$field] ?? null;

            // Un criterio vacío no restringe, como en una pantalla de selección.
            if ($value === null || $value === [] || $value === '') {
                continue;
            }

            $column = $query->qualifyColumn($field);

            match ($mode) {
                FilterMode::Exact => $query->where($column, $value),
                FilterMode::Partial => $query->whereLike($column, '%'.self::escapeLike(self::asString($value)).'%'),
                FilterMode::In => $query->whereIn($column, (array) $value),
                FilterMode::Custom => $this->applyCustomFilter($query, $field, $value),
            };
        }
    }

    /**
     * Aplica un filtro declarado con FilterMode::Custom, con el valor ya
     * validado y no vacío. El listado que declara uno redefine este método.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyCustomFilter(Builder $query, string $field, mixed $value): void
    {
        throw new LogicException(static::class." declara el filtro {$field} como Custom sin aplicarlo.");
    }

    private static function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Escapa los comodines de LIKE para que el valor se busque literal. La
     * barra invertida es el carácter de escape por defecto de PostgreSQL.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
