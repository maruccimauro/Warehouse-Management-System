# ADR-010: Errores HTTP con Problem Details y códigos de dominio estables

- Estado: Aceptado
- Fecha: 22/11/2026
- Aceptado: 23/11/2026, al implementarse en E1.06

## Contexto

El frontend necesita distinguir errores de validación, de autorización y de negocio (stock insuficiente, ubicación bloqueada) para mostrar mensajes y decidir si reintentar.

## Decisión

Todas las respuestas de error de la API usan el formato Problem Details (RFC 9457), con tipo de contenido `application/problem+json` y un campo de extensión `code` estable (`INSUFFICIENT_STOCK`, `LOCATION_BLOCKED`, `IDEMPOTENCY_KEY_REUSED`, etc.). El cliente decide por `code`, nunca por `title` ni por `detail`.

| Miembro | Contenido |
| - | - |
| `type` | `urn:wms:problem:{código en minúsculas con guiones}` para los errores de dominio; `about:blank` para los errores HTTP genéricos, cuyo `title` es el texto del estado HTTP |
| `title` | Resumen del tipo de problema, igual en todas sus ocurrencias |
| `status` | Estado HTTP |
| `detail` | Descripción de la ocurrencia concreta |
| `instance` | Ruta de la petición |
| `code` | Código estable |
| `context` | Datos de la ocurrencia que el cliente puede usar (por ejemplo, la cantidad disponible). Solo en errores de dominio |
| `errors` | Errores por campo. Solo en `VALIDATION_FAILED` |

Las excepciones de dominio extienden `App\Shared\Exceptions\DomainException`, que define código, título y estado HTTP (422 por defecto, 409 para conflictos de estado). Un único renderer, `App\Shared\Exceptions\ProblemDetailsRenderer`, traduce todas las excepciones. Las de dominio no se reportan como fallas en el log porque son respuestas esperadas.

Códigos genéricos, fuera del catálogo de dominio de `business-rules.md`:

| Código | HTTP | Origen |
| - | - | - |
| `VALIDATION_FAILED` | 422 | Form Requests y validación |
| `UNAUTHENTICATED` | 401 | Sin sesión |
| `FORBIDDEN` | 403 | Policies. Se conserva el mensaje de la policy, que está escrito para el usuario |
| `NOT_FOUND` | 404 | Ruta o recurso inexistente, incluido el recurso de un depósito ajeno (ADR-009) |
| `METHOD_NOT_ALLOWED` | 405 | Con cabecera `Allow` |
| `CSRF_TOKEN_MISMATCH` | 419 | Token CSRF inválido o expirado |
| `TOO_MANY_REQUESTS` | 429 | Con cabecera `Retry-After` |
| `INTERNAL_ERROR` | 500 | Cualquier error no previsto |

Salvo en 403, los errores HTTP usan un `detail` genérico en lugar del mensaje de la excepción, que puede exponer nombres de clases o rutas internas. Un 500 no incluye detalles internos; con `APP_DEBUG` activo agrega un miembro `debug` con la clase, el mensaje y la ubicación de la excepción, solo para desarrollo.

Los errores que garantiza la base de datos se traducen en el mismo punto, con `App\Shared\Database\DatabaseErrorTranslator`:

- Violaciones de restricciones (SQLSTATE clase 23): por el nombre de la restricción, que sigue la convención `{tabla}_{columnas}_{tipo}`. Por ejemplo, `stocks_quantity_on_hand_check` se traduce a 422 `INSUFFICIENT_STOCK`.
- Excepciones de triggers: por el mensaje, que es el código de dominio (`RAISE EXCEPTION 'REVERSAL_EXCEEDS_REMAINING' USING ERRCODE = 'check_violation'`).
- Cada módulo declara sus traducciones en su ServiceProvider (`$constraintErrors` y `$triggerErrors`). Registrar dos veces la misma restricción o el mismo código es un error de arranque.
- Una violación sin traducción es un error de programación: responde 500 sin detalles internos y se reporta. Es el caso de `REVERSAL_LINE_MISMATCH` y `REVERSAL_LINE_WITHOUT_ORIGINAL`, que la aplicación nunca debe provocar y no forman parte del catálogo público. Las violaciones traducidas no se reportan.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Formato por defecto de Laravel | No distingue errores de negocio y obliga a interpretar mensajes |
| Formato propio | Reinventa un estándar existente |
| Traducir los errores de la base dentro de cada Action | Repite el mapeo en cada operación y deja errores sin traducir cuando una Action nueva lo olvida |

## Consecuencias

- Contrato de errores documentable en OpenAPI y tipado en el frontend (`ApiError`, E1.09).
- Los códigos forman parte de la API pública: cambiarlos es un cambio incompatible.
- En E3, una prueba verifica que toda restricción y todo mensaje de trigger con código público tenga traducción, a partir de `mappedConstraints()` y `mappedTriggerCodes()` del traductor.
- Enmendado el 23/11/2026 en E0.09: traducción de los errores de la base de datos.
- Aceptado el 23/11/2026 con lo implementado en E1.06: miembros del formato, esquema de `type`, códigos genéricos, tratamiento del modo debug y registro de traducciones por módulo.
