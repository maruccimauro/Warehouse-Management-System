<?php

// Error contract texts (ADR-010). Clients decide by code; these texts are the
// readable fallback for title and detail.
return [
    'validation' => [
        'title' => 'The given data was invalid.',
        'detail' => 'One or more fields have errors.',
    ],
    'http' => [
        401 => 'An active session is required.',
        404 => 'The requested resource does not exist.',
        405 => 'The HTTP method is not allowed for this resource.',
        419 => 'The CSRF token is invalid or expired. Obtain a new one at /sanctum/csrf-cookie.',
        429 => 'Too many requests. Please try again later.',
    ],
    'internal' => 'An unexpected error occurred.',
    'value_already_exists' => 'The value is already registered.',
    'invalid_state_transition' => [
        'title' => 'The state change is not allowed.',
        'detail' => 'The resource is in state :from and cannot move to :to.',
    ],
    'idempotency_key_reused' => 'The idempotency key was already used with different content.',
    'idempotency_request_in_progress' => 'Another request with the same idempotency key is still in progress.',
    'concurrency_conflict' => [
        'title' => 'The operation collided with a simultaneous one.',
        'detail' => 'Another operation was using the same data and this one could not complete after several attempts. Try again in a few seconds.',
    ],
];
