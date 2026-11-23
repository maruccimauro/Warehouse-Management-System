<?php

/*
| Configuración propia del WMS.
|
| dev_admin: usuario administrador que crean los seeders de desarrollo
| (E1.12). Solo se siembra en los entornos local y testing: estas credenciales
| son públicas y nunca deben existir en producción.
*/

return [
    'dev_admin' => [
        'name' => env('WMS_DEV_ADMIN_NAME', 'Administrador de desarrollo'),
        'email' => env('WMS_DEV_ADMIN_EMAIL', 'admin@wms.test'),
        'password' => env('WMS_DEV_ADMIN_PASSWORD', 'admin-dev-1'),
    ],

    // Usuarios ficticios para probar listados y búsquedas (usuario01@wms.test en adelante).
    'demo_users' => (int) env('WMS_DEMO_USERS', 10),
];
