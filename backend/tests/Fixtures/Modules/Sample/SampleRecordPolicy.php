<?php

namespace Tests\Fixtures\Modules\Sample;

class SampleRecordPolicy
{
    public function view(): bool
    {
        return true;
    }
}
