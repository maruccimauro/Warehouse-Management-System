<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Audit\Services\NullAuditLogger;
use App\Shared\Providers\ModuleServiceProvider;

/**
 * Módulo Audit: Registro de auditoría de las operaciones.
 */
class AuditServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        AuditLogger::class => NullAuditLogger::class,
    ];
}
