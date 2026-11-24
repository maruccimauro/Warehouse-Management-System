<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;

class UnitOfMeasureListRequest extends ListRequest
{
    protected function filters(): array
    {
        return [
            'code' => FilterMode::Partial,
            'name' => FilterMode::Partial,
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
}
