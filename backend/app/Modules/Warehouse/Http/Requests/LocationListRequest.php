<?php

namespace App\Modules\Warehouse\Http\Requests;

use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Http\Requests\Concerns\ResolvesRouteModels;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LocationListRequest extends ListRequest
{
    use ResolvesRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('warehouse'));
    }

    protected function filters(): array
    {
        return [
            'code' => FilterMode::Selection,
            'zone_id' => FilterMode::In,
            'status' => FilterMode::In,
            'aisle' => FilterMode::Exact,
            'rack' => FilterMode::Exact,
            'level' => FilterMode::Exact,
            'is_system' => FilterMode::Exact,
        ];
    }

    protected function sorts(): array
    {
        return ['code', 'aisle', 'rack', 'level', 'status'];
    }

    protected function defaultSort(): string
    {
        return 'code';
    }

    protected function filterValueRules(): array
    {
        return [
            'zone_id' => ['integer', 'min:1'],
            'status' => [Rule::enum(LocationStatus::class)],
            'is_system' => ['boolean'],
        ];
    }
}
