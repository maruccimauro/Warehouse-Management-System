<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Actions\GenerateLocations;
use App\Modules\Warehouse\Data\LocationGrid;
use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Plantilla de generación masiva: pasillos por letra y estanterías, niveles y
 * posiciones (opcionales) por número, cada uno con desde y hasta.
 */
class GenerateLocationsRequest extends FormRequest
{
    use ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('create', [Location::class, $this->warehouse()]);
    }

    protected function prepareForValidation(): void
    {
        $aisles = $this->input('aisles');

        if (is_array($aisles)) {
            $this->merge(['aisles' => array_map(fn (mixed $value): mixed => is_string($value) ? mb_strtoupper(trim($value)) : $value, $aisles)]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $number = ['integer', 'min:1', 'max:'.LocationGrid::MAX_NUMBER];

        return [
            'zone_id' => ['required', 'integer', StoreLocationRequest::userZoneOf($this->warehouse()->id)],
            'aisles' => ['required', 'array:from,to'],
            'aisles.from' => ['required', 'string', 'regex:/^[A-Z]$/'],
            'aisles.to' => ['required', 'string', 'regex:/^[A-Z]$/'],
            'racks' => ['required', 'array:from,to'],
            'racks.from' => ['required', ...$number],
            'racks.to' => ['required', ...$number, 'gte:racks.from'],
            'levels' => ['required', 'array:from,to'],
            'levels.from' => ['required', ...$number],
            'levels.to' => ['required', ...$number, 'gte:levels.from'],
            'positions' => ['sometimes', 'nullable', 'array:from,to'],
            'positions.from' => ['required_with:positions', ...$number],
            'positions.to' => ['required_with:positions', ...$number, 'gte:positions.from'],
            'max_units' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Validaciones que dependen de varios campos: el orden de los pasillos y el
     * tamaño del lote.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->string('aisles.to')->toString() < $this->string('aisles.from')->toString()) {
                    $validator->errors()->add('aisles.to', __('warehouse.aisle_range_inverted'));

                    return;
                }

                if ($this->grid()->count() > GenerateLocations::MAX_LOCATIONS) {
                    $validator->errors()->add('aisles', __('warehouse.too_many_locations', ['max' => GenerateLocations::MAX_LOCATIONS]));
                }
            },
        ];
    }

    public function grid(): LocationGrid
    {
        $positions = $this->input('positions');

        return new LocationGrid(
            aisles: [$this->string('aisles.from')->toString(), $this->string('aisles.to')->toString()],
            racks: [$this->integer('racks.from'), $this->integer('racks.to')],
            levels: [$this->integer('levels.from'), $this->integer('levels.to')],
            positions: is_array($positions) ? [$this->integer('positions.from'), $this->integer('positions.to')] : null,
        );
    }

    public function maxUnits(): ?int
    {
        return $this->filled('max_units') ? $this->integer('max_units') : null;
    }

    public function zone(): Zone
    {
        return $this->warehouse()->zones()->findOrFail($this->integer('zone_id'));
    }
}
