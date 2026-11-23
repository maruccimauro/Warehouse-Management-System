<?php

namespace Tests\Fixtures\Modules\Sample;

use App\Shared\Providers\ModuleServiceProvider;

/**
 * Módulo ficticio con rutas, migraciones, policies y traducciones de errores
 * de base de datos para probar las convenciones de ModuleServiceProvider y el
 * contrato de errores sin depender de los módulos reales.
 */
class SampleServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        SampleRecord::class => SampleRecordPolicy::class,
    ];

    protected array $constraintErrors = [
        'sample_quantity_check' => SampleRuleViolated::class,
    ];

    protected array $uniqueConstraintFields = [
        'sample_items_code_unique' => 'code',
    ];

    protected array $triggerErrors = [
        'SAMPLE_TRIGGER_CONFLICT' => SampleConflict::class,
    ];
}
