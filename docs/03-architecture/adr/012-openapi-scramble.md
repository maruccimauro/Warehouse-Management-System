# ADR-012: Documentación OpenAPI generada con Scramble

- Estado: Aceptado
- Fecha: 22/11/2026
- Aceptado: 24/11/2026, al implementarse en E2.11
- Relacionados: ADR-007, ADR-009, ADR-010

## Contexto

El proyecto requiere documentación OpenAPI de la API REST. La documentación mantenida a mano tiende a quedar desactualizada respecto del código.

## Decisión

Se usa `dedoc/scramble`, que genera la especificación a partir de las rutas, los Form Requests y los API Resources sin anotaciones. Se agregan extensiones para documentar las respuestas Problem Details con sus códigos de dominio y la cabecera `Idempotency-Key`.

### Versión y alcance

- `dedoc/scramble ^0.13.55` como dependencia de desarrollo. El paquete no salió de 0.x; la rama 0.13 tiene recorrido suficiente para el criterio de versiones del stack y el caret fija la versión menor. Sin el paquete (imagen de producción con `--no-dev`), `App\Providers\ApiDocumentationServiceProvider` no hace nada.
- Se documenta solo la API versionada (`api_path` en `api/v1`) con un servidor relativo `/api/v1`, para que la especificación no dependa del entorno que la genera.

### Publicación

| Pieza | Decisión |
| - | - |
| Interfaz | Stoplight Elements en `/api/docs` y el JSON en `/api/docs.json`, fuera de `/docs/`, que es de la wiki (E2.15). Las rutas existen solo con `WMS_API_DOCS_ENABLED=true` y fuera de producción: en producción responden 404, aunque la variable esté activa |
| Especificación versionada | `docs/03-architecture/openapi.json`, generada con `composer openapi`. El CI la regenera y falla si difiere de la versionada, así todo cambio de contrato queda en el diff del commit |

### Errores (ADR-010)

| Pieza | Función |
| - | - |
| `ProblemResponses` | Esquema compartido `ProblemDetails` y un esquema por código (`ProblemLastAdministrator`, por ejemplo) con `type`, `status` y `code` fijos. Las respuestas de una operación con el mismo estado se agrupan en un `anyOf` de esos esquemas |
| `HttpProblemToResponse` | Reemplaza las respuestas que Scramble documenta con el formato de Laravel por las que produce `ProblemDetailsRenderer`: 401, 403, 404, 419, 422 `VALIDATION_FAILED` con `errors`, 429 con `Retry-After` y los errores HTTP |
| `DomainExceptionToResponse` | Cada `DomainException` con su estado, código y título, leídos de una instancia creada sin constructor (no dependen de la ocurrencia) |
| `CalledMethodExceptions` | Scramble solo ve los `throw` y los `@throws` del método del controlador. Esta extensión propaga a quien llama las excepciones de los métodos de clases de `App` (Actions invocables, servicios) y de los closures pasados como argumento (`DB::transaction`). La propagación es transitiva |
| `SpaSessionOperation` y `SpaSessionDocument` (módulo `Identity`) | Esquemas de seguridad de la SPA (cookie de sesión y `X-XSRF-TOKEN`), 419 `CSRF_TOKEN_MISMATCH` en los métodos que modifican y 403 `PASSWORD_CHANGE_REQUIRED` en las rutas que `RequirePasswordChange` no admite |

Lo que el análisis no puede ver se declara con `@throws` en el método que lo origina. El caso de E2 es `VALUE_ALREADY_EXISTS`, que produce `DatabaseErrorTranslator` desde la restricción única de la base: lo declaran las Actions que escriben una columna con restricción única registrada.

### Idempotencia (ADR-007)

El contrato se fija antes del middleware: una ruta que modifica stock declara el middleware con el alias `App\Shared\Http\Idempotency\Idempotency::MIDDLEWARE` (`idempotent`). `IdempotencyKeyOperation` reconoce el alias y documenta la cabecera obligatoria `Idempotency-Key` y los errores 422 `IDEMPOTENCY_KEY_REUSED` y 409 `IDEMPOTENCY_REQUEST_IN_PROGRESS`, cuyas excepciones ya existen en `App\Shared\Exceptions`. E3 implementa el middleware con ese alias.

### Cuerpos de petición

`ProhibitedFieldRule` quita del cuerpo documentado los campos cuya única regla es `prohibited` (código inmutable, SKU), que Scramble documentaría como aceptados de tipo desconocido. Un `prohibited` condicional no se toca.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Scribe (`knuckleswtf/scribe`) | Requiere regenerar y mantener comentarios; aporta ejemplos de respuesta, pero con más mantenimiento |
| L5 Swagger con anotaciones | Gran volumen de anotaciones repetidas y desincronización frecuente con el código |
| Atributo `#[Response]` por método de controlador para los errores | Control total, pero es mantenimiento a mano que se desincroniza |
| Solo un esquema genérico con `code: string` | No dice qué errores devuelve cada operación, que es lo que necesita el frontend |
| Interfaz pública en producción | Expone la superficie de la API y el "Try it" opera contra producción con una sesión real |
| Scalar como interfaz | Envía las peticiones de prueba por un proxy externo por defecto |

## Consecuencias

- Documentación alineada con las validaciones reales y con los códigos que cada operación puede devolver.
- Una prueba (`tests/Feature/OpenApi/OpenApiSpecificationTest.php`) verifica que todo error 4xx documentado sea `application/problem+json` y que todo código documentado figure en el catálogo de `business-rules.md` o entre los genéricos de ADR-010, con el mismo estado.
- Un cambio de contrato exige regenerar `openapi.json`; el CI lo controla.
- Lo que Scramble no infiere se documenta con sus mecanismos de extensión.
- Aceptado el 24/11/2026 con lo implementado en E2.11 (decisiones del usuario D1 a D4).
