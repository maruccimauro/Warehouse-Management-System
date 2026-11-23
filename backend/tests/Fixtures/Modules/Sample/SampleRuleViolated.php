<?php

namespace Tests\Fixtures\Modules\Sample;

use App\Shared\Exceptions\DomainException;

class SampleRuleViolated extends DomainException
{
    public function errorCode(): string
    {
        return 'SAMPLE_RULE_VIOLATED';
    }

    public function title(): string
    {
        return 'La regla de ejemplo no se cumple.';
    }
}
