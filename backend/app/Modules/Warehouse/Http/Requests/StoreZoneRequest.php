<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Http\Requests\Concerns\NormalizesCodes;
use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreZoneRequest extends FormRequest
{
    use NormalizesCodes, ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('create', [Zone::class, $this->warehouse()]);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCodes(['code']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:'.self::CODE_PATTERN, Rule::unique('zones', 'code')->where('warehouse_id', $this->warehouse()->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ZoneType::class)],
        ];
    }
}
