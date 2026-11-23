<?php

namespace Tests\Fixtures\Modules\Sample;

use App\Shared\Providers\ModuleServiceProvider;

/**
 * Módulo ficticio con rutas, migraciones y policies para probar la convención
 * de ModuleServiceProvider sin depender de los módulos reales.
 */
class SampleServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        SampleRecord::class => SampleRecordPolicy::class,
    ];
}
