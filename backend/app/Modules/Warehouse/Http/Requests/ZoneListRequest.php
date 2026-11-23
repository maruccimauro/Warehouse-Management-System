<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ZoneListRequest extends ListRequest
{
    use ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('warehouse'));
    }

    protected function filters(): array
    {
        return [
            'code' => FilterMode::Partial,
            'name' => FilterMode::Partial,
            'type' => FilterMode::In,
            'is_system' => FilterMode::Exact,
        ];
    }

    protected function sorts(): array
    {
        return ['code', 'name', 'type'];
    }

    protected function defaultSort(): string
    {
        return 'code';
    }

    protected function filterValueRules(): array
    {
        return [
            'type' => [Rule::enum(ZoneType::class)],
            'is_system' => ['boolean'],
        ];
    }
}
