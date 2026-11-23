<?php

namespace Tests\Fixtures\Modules\Sample;

use App\Shared\Exceptions\DomainException;

class SampleConflict extends DomainException
{
    public function errorCode(): string
    {
        return 'SAMPLE_CONFLICT';
    }

    public function title(): string
    {
        return 'El recurso de ejemplo está en un estado incompatible.';
    }

    public function status(): int
    {
        return 409;
    }
}
