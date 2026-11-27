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

    // Idempotencia por petición (ADR-007): espera máxima de una petición
    // cuya clave está en uso por otra en curso (luego 409
    // IDEMPOTENCY_REQUEST_IN_PROGRESS) y días que se conservan las claves.
    'idempotency' => [
        'lock_timeout_ms' => (int) env('WMS_IDEMPOTENCY_LOCK_TIMEOUT_MS', 5000),
        'retention_days' => (int) env('WMS_IDEMPOTENCY_RETENTION_DAYS', 7),
    ],

    // Bandeja de entrada (E3.27): días que se conservan los mensajes
    // archivados antes de la limpieza programada (D16.13).
    'inbox' => [
        'archived_retention_days' => (int) env('WMS_INBOX_ARCHIVED_RETENTION_DAYS', 90),
    ],

    // Interfaz de la documentación OpenAPI en /api/docs (ADR-012). Nunca se
    // registra en producción, aunque la variable esté activa.
    'api_docs' => [
        'enabled' => (bool) env('WMS_API_DOCS_ENABLED', false),
    ],
];
