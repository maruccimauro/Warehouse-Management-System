<?php

use App\Shared\Database\DatabaseErrorTranslator;
use Tests\Fixtures\Modules\Sample\SampleConflict;
use Tests\Fixtures\Modules\Sample\SampleRuleViolated;

it('rejects a second translation for the same constraint', function () {
    $translator = new DatabaseErrorTranslator;
    $translator->mapConstraint('stocks_quantity_on_hand_check', SampleRuleViolated::class);

    $translator->mapConstraint('stocks_quantity_on_hand_check', SampleConflict::class);
})->throws(InvalidArgumentException::class, 'ya tiene traducción');

it('rejects a unique constraint already mapped to a domain exception', function () {
    $translator = new DatabaseErrorTranslator;
    $translator->mapConstraint('sample_items_code_unique', SampleConflict::class);

    $translator->mapUniqueConstraint('sample_items_code_unique', 'code');
})->throws(InvalidArgumentException::class, 'ya tiene traducción');

it('rejects a second field for the same unique constraint', function () {
    $translator = new DatabaseErrorTranslator;
    $translator->mapUniqueConstraint('sample_items_code_unique', 'code');

    $translator->mapUniqueConstraint('sample_items_code_unique', 'name');
})->throws(InvalidArgumentException::class, 'ya tiene traducción');

it('rejects a unique translation for a constraint without the unique suffix', function () {
    (new DatabaseErrorTranslator)->mapUniqueConstraint('sample_items_code_check', 'code');
})->throws(InvalidArgumentException::class, '_unique');

it('rejects a trigger code that is not a domain code', function () {
    (new DatabaseErrorTranslator)->mapTriggerCode('Table % is append only', SampleConflict::class);
})->throws(InvalidArgumentException::class, 'formato de código de dominio');
