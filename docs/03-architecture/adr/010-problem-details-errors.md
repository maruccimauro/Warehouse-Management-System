# ADR-010: Errores HTTP con Problem Details y códigos de dominio estables

- Estado: Propuesto
- Fecha: 22/11/2026

## Contexto

El frontend necesita distinguir errores de validación, de autorización y de negocio (stock insuficiente, ubicación bloqueada) para mostrar mensajes y decidir si reintentar.

## Decisión

Todas las respuestas de error usan el formato Problem Details (RFC 9457) con un campo `code` estable (`INSUFFICIENT_STOCK`, `LOCATION_BLOCKED`, `IDEMPOTENCY_KEY_REUSED`, etc.). Las excepciones de dominio extienden una clase base con su código y estado HTTP, y el manejador de excepciones las traduce de forma centralizada.

Los errores que garantiza la base de datos se traducen en el mismo punto:

- Violaciones de restricciones (`CHECK`, `UNIQUE`, claves foráneas): por el nombre de la restricción, que sigue la convención `{tabla}_{columnas}_{tipo}`. Por ejemplo, `stocks_quantity_on_hand_check` se traduce a 422 `INSUFFICIENT_STOCK`.
- Excepciones de triggers: por el mensaje, que es el código de dominio. `REVERSAL_EXCEEDS_REMAINING` se traduce a 422.
- Una violación sin traducción es un error de programación y responde 500 sin detalles internos. Es el caso de `REVERSAL_LINE_MISMATCH` y `REVERSAL_LINE_WITHOUT_ORIGINAL`, que la aplicación nunca debe provocar y no forman parte del catálogo público.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Formato por defecto de Laravel | No distingue errores de negocio y obliga a interpretar mensajes |
| Formato propio | Reinventa un estándar existente |

## Consecuencias

- Contrato de errores documentable en OpenAPI y tipado en el frontend.
- Los códigos forman parte de la API pública: cambiarlos es un cambio incompatible.
- Una prueba verifica que toda restricción y todo mensaje de trigger con código público tenga traducción.
- Enmendado el 23/11/2026 en E0.09: traducción de los errores de la base de datos.
