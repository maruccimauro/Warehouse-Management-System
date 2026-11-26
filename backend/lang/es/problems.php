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
    'invalid_state_transition' => [
        'title' => 'El cambio de estado no está permitido.',
        'detail' => 'El recurso está en estado :from y no puede pasar a :to.',
    ],
    'idempotency_key_reused' => 'La clave de idempotencia ya se usó con otro contenido.',
    'idempotency_request_in_progress' => 'Otra solicitud con la misma clave de idempotencia sigue en curso.',
    'concurrency_conflict' => [
        'title' => 'La operación chocó con otra simultánea.',
        'detail' => 'Otra operación usaba los mismos datos y no se pudo completar después de varios intentos. Reintente en unos segundos.',
    ],
];
