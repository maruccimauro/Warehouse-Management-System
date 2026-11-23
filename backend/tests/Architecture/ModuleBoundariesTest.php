<?php

/*
| Límites entre módulos (ADR-001). Se mantienen por convención y por estas
| pruebas, no por la red: son la red de seguridad de esa convención.
*/

const MODULES = [
    'Audit', 'Catalog', 'Fulfillment', 'Identity', 'Inventory',
    'Procurement', 'Receiving', 'Reporting', 'Stocktaking', 'Warehouse',
];

function module_namespace(string $module, string $suffix = ''): string
{
    return 'App\\Modules\\'.$module.($suffix !== '' ? '\\'.$suffix : '');
}

arch('shared code does not depend on domain modules')
    ->expect('App\\Shared')
    ->not->toUse('App\\Modules');

foreach (MODULES as $module) {
    $otherHttpLayers = array_map(
        fn (string $other): string => module_namespace($other, 'Http'),
        array_values(array_diff(MODULES, [$module])),
    );

    arch("{$module} does not use the HTTP layer of another module")
        ->expect(module_namespace($module))
        ->not->toUse($otherHttpLayers);
}

// Única puerta de escritura sobre el stock (regla 2 del plan, ADR-002): los
// modelos de Inventory solo se usan dentro del módulo y en Reporting, que lee.
// El resto de los módulos invoca las Actions de Inventory.
arch('inventory models are only used by inventory and reporting')
    ->expect(module_namespace('Inventory', 'Models'))
    ->toOnlyBeUsedIn([module_namespace('Inventory'), module_namespace('Reporting')]);

arch('application code does not use debugging functions')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'var_export', 'die', 'exit'])
    ->not->toBeUsed();

arch('application code passes the php preset')
    ->preset()->php();

arch('application code passes the security preset')
    ->preset()->security();
