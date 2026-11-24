<?php

namespace Tests\Fixtures\Modules\Sample;

use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;

class SampleRecordListRequest extends ListRequest
{
    protected function filters(): array
    {
        return [
            'code' => FilterMode::Exact,
            'name' => FilterMode::Partial,
            'status' => FilterMode::In,
            'is_active' => FilterMode::Exact,
            'section' => FilterMode::Selection,
            'rank' => FilterMode::Selection,
        ];
    }

    protected function sorts(): array
    {
        return ['code', 'name'];
    }

    protected function defaultSort(): string
    {
        return 'code';
    }

    protected function filterValueRules(): array
    {
        return ['is_active' => ['boolean'], 'rank' => ['integer']];
    }
}
