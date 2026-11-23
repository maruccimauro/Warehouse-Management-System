<?php

namespace Tests\Fixtures\Modules\Sample;

use Illuminate\Foundation\Http\FormRequest;

class SampleRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
