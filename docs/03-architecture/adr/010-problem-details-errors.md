# ADR-010: Errores HTTP con Problem Details y códigos de dominio estables

- Estado: Propuesto
- Fecha: 22/11/2026

## Contexto

El frontend necesita distinguir errores de validación, de autorización y de negocio (stock insuficiente, ubicación bloqueada) para mostrar mensajes y decidir si reintentar.

## Decisión

Todas las respuestas de error usan el formato Problem Details (RFC 9457) con un campo `code` estable (`INSUFFICIENT_STOCK`, `LOCATION_BLOCKED`, `IDEMPOTENCY_KEY_REUSED`, etc.). Las excepciones de dominio extienden una clase base con su código y estado HTTP, y el manejador de excepciones las traduce de forma centralizada.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Formato por defecto de Laravel | No distingue errores de negocio y obliga a interpretar mensajes |
| Formato propio | Reinventa un estándar existente |

## Consecuencias

- Contrato de errores documentable en OpenAPI y tipado en el frontend.
- Los códigos forman parte de la API pública: cambiarlos es un cambio incompatible.
