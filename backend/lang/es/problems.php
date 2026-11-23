<?php

// Textos del contrato de errores (ADR-010). El cliente decide por code; estos
// textos son el respaldo legible de title y detail.
return [
    'validation' => [
        'title' => 'Los datos enviados no son válidos.',
        'detail' => 'Uno o más campos tienen errores.',
    ],
    'http' => [
        401 => 'Se requiere una sesión iniciada.',
        404 => 'El recurso solicitado no existe.',
        405 => 'El método HTTP no está permitido para este recurso.',
        419 => 'El token CSRF no es válido o expiró. Se debe obtener uno nuevo en /sanctum/csrf-cookie.',
        429 => 'Se superó el límite de solicitudes. Intente nuevamente más tarde.',
    ],
    'internal' => 'Ocurrió un error inesperado.',
    'value_already_exists' => 'El valor ya está registrado.',
];
