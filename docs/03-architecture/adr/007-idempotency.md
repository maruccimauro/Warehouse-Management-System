# ADR-007: Idempotencia en operaciones que modifican stock

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-004, ADR-013

## Contexto

Los operadores trabajan con conexiones inestables y botones que pueden presionarse dos veces. Un reintento de red sobre una transferencia o un despacho no debe duplicar la operación.

## Decisión

- Los endpoints que modifican stock exigen la cabecera `Idempotency-Key`, generada por el cliente al iniciar el intento de operación y reutilizada en sus reintentos.
- La tabla `idempotency_keys` guarda `(user_id, key)` único, el método, la ruta, el hash del cuerpo y la respuesta.
- Misma clave y mismo cuerpo: se devuelve la respuesta almacenada. Misma clave con otro cuerpo: 422 `IDEMPOTENCY_KEY_REUSED`. Clave en uso por otra petición en curso: la segunda espera en el índice único hasta que la primera termina; si la espera supera `lock_timeout`, 409 `IDEMPOTENCY_REQUEST_IN_PROGRESS`.
- La clave y la respuesta se registran en la misma transacción que la operación. Una petición rechazada por una regla de negocio no deja clave, y su reintento se evalúa de nuevo.
- Segunda barrera por documento: `inventory_operations_reference_unique (reference_type, reference_id)` impide que una recepción, una tarea de guardado, una línea de picking, un despacho o un inventario físico originen dos operaciones, aunque la segunda petición llegue con otra clave (INV-007). El `operation_id UNIQUE` de los documentos solo impide que dos documentos compartan operación; se conserva para navegar.
- Las reversiones no tienen esa barrera por documento, porque una posición se revierte varias veces de forma legítima (ADR-013): dos 602 por 5 unidades del mismo despacho son dos devoluciones reales. Las distingue la clave: con la misma clave, la segunda petición devuelve la respuesta almacenada; con otra clave, es una reversión nueva, limitada por el saldo de cada línea.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Deshabilitar el botón en el frontend | Necesario pero insuficiente: no cubre reintentos de red |
| Detectar duplicados por similitud de contenido | Ambiguo: dos transferencias o dos reversiones parciales idénticas pueden ser legítimas |

## Consecuencias

- Reintentos seguros desde el cliente.
- Tabla adicional con limpieza programada de claves antiguas.
- Doble defensa en la confirmación de documentos: idempotencia por petición y unicidad por documento.
- Riesgo aceptado en reversiones: si el cliente reenvía una reversión con una clave nueva, por ejemplo tras recargar la pantalla, se aplica otra vez mientras quede saldo, igual que dos 102 contabilizados en SAP. Se mitiga generando la clave por intención de operación, no por envío, y mostrando el saldo revertible antes de confirmar.
- Enmendado el 23/11/2026 en E0.09: barrera por referencia de la operación, espera ante clave en curso y tratamiento de reversiones parciales repetidas.
- Enmendado el 24/11/2026 en E2.11: el contrato del middleware queda fijado antes de su implementación para documentarlo en OpenAPI (ADR-012). Alias `idempotent` (`App\Shared\Http\Idempotency\Idempotency::MIDDLEWARE`), cabecera `Idempotency-Key` y excepciones `IdempotencyKeyReused` (422) e `IdempotencyRequestInProgress` (409) en `App\Shared\Exceptions`. E3 implementa el middleware con ese alias. Queda para E3 la respuesta ante una petición sin la cabecera.
