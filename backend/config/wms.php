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
        'username' => env('WMS_DEV_ADMIN_USERNAME', 'ADMIN'),
        'name' => env('WMS_DEV_ADMIN_NAME', 'Administrador de desarrollo'),
        'email' => env('WMS_DEV_ADMIN_EMAIL', 'admin@wms.test'),
        'password' => env('WMS_DEV_ADMIN_PASSWORD', 'admin-dev-1'),
    ],

    // Usuarios ficticios para probar listados y búsquedas, hasta 10 (U000001,
    // maria.martinez@wms.test, en adelante; IdentitySeeder::DEMO_USER_NAMES).
    'demo_users' => (int) env('WMS_DEMO_USERS', 10),

    // Interfaz de la documentación OpenAPI en /api/docs (ADR-012). Nunca se
    // registra en producción, aunque la variable esté activa.
    'api_docs' => [
        'enabled' => (bool) env('WMS_API_DOCS_ENABLED', false),
    ],
];
