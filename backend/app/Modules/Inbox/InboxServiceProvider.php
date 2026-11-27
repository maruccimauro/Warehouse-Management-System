<?php

namespace App\Modules\Inbox;

use App\Modules\Inbox\Console\PruneInboxMessages;
use App\Modules\Inbox\Contracts\Inbox;
use App\Modules\Inbox\Services\DatabaseInbox;
use App\Shared\Providers\ModuleServiceProvider;

/**
 * Módulo Inbox: bandeja de entrada interna de cada usuario (E3.27, D16.5).
 *
 * Los demás módulos dejan avisos con el contrato Inbox; el usuario los lee en
 * la transacción Bandeja de entrada (SBWP) y en el contador del encabezado.
 */
class InboxServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        Inbox::class => DatabaseInbox::class,
    ];

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([PruneInboxMessages::class]);
        }
    }
}
