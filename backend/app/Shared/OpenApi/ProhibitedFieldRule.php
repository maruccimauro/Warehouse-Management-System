<?php

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;

/**
 * Un campo con la regla prohibited como única regla (el código inmutable de
 * un depósito, el SKU de un producto) no se acepta: enviarlo responde 422
 * VALIDATION_FAILED. Scramble lo documentaría como un campo de tipo
 * desconocido, que se lee como aceptado; acá se quita del cuerpo.
 *
 * Un prohibited condicional (prohibited_if, o el de un ternario que en otros
 * casos acepta el campo) no se toca: el campo existe para algunos recursos.
 */
final class ProhibitedFieldRule implements AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('prohibited');
    }

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        if ($context->fieldRules->count() === 1) {
            $schemaBag->remove($context->field);
        }
    }
}
