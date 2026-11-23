<?php

use App\Shared\Database\DatabaseErrorTranslator;
use Tests\Fixtures\Modules\Sample\SampleConflict;
use Tests\Fixtures\Modules\Sample\SampleRuleViolated;

it('rejects a second translation for the same constraint', function () {
    $translator = new DatabaseErrorTranslator;
    $translator->mapConstraint('stocks_quantity_on_hand_check', SampleRuleViolated::class);

    $translator->mapConstraint('stocks_quantity_on_hand_check', SampleConflict::class);
})->throws(InvalidArgumentException::class, 'ya tiene traducción');

it('rejects a trigger code that is not a domain code', function () {
    (new DatabaseErrorTranslator)->mapTriggerCode('Table % is append only', SampleConflict::class);
})->throws(InvalidArgumentException::class, 'formato de código de dominio');
