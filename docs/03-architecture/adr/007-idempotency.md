# ADR-007: Idempotencia en operaciones que modifican stock

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-004, ADR-013

## Contexto

Los operadores trabajan con conexiones inestables y botones que pueden presionarse dos veces. Un reintento de red sobre una transferencia o un despacho no debe duplicar la operación.

## Decisión

- Los endpoints que modifican stock exigen la cabecera `Idempotency-Key`, generada por el cliente al iniciar el intento de operación y reutilizada en sus reintentos.
- Una petición sin la cabecera, o con una clave vacía o de más de 100 caracteres, responde 428 `IDEMPOTENCY_KEY_REQUIRED` sin ejecutar nada: una petición sin clave no tiene protección ante el reenvío, y aceptarla en silencio deja la garantía de INV-007 a criterio de cada cliente.
- La tabla `idempotency_keys` guarda `(user_id, key)` único, el método, la ruta, el hash del cuerpo y la respuesta.
- Misma clave y mismo cuerpo: se devuelve la respuesta almacenada. Misma clave con otro cuerpo: 422 `IDEMPOTENCY_KEY_REUSED`. Clave en uso por otra petición en curso: la segunda espera en el índice único hasta que la primera termina; si la espera supera `lock_timeout`, 409 `IDEMPOTENCY_REQUEST_IN_PROGRESS`.
- La clave y la respuesta se registran en la misma transacción que la operación. Una petición rechazada por una regla de negocio no deja clave, y su reintento se evalúa de nuevo.
- Segunda barrera por documento: `inventory_operations_reference_unique (reference_type, reference_id)` impide que una recepción, una tarea de guardado, una línea de picking, un despacho o un inventario físico originen dos operaciones, aunque la segunda petición llegue con otra clave (INV-007). Enmienda del 26/11/2026 (E3.05): es un índice único parcial que excluye el ajuste de stock, que origina una operación al crearse y una por cada posición aprobada; para ese documento la barrera es la posición, cuyo `operation_id` se asigna una sola vez y cuyo trigger solo admite pasar de `pending` a `approved` o `rejected`. El `operation_id UNIQUE` de los documentos solo impide que dos documentos compartan operación; se conserva para navegar.
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
- Enmendado el 25/11/2026 al planificar E3: una petición sin la cabecera responde 428 `IDEMPOTENCY_KEY_REQUIRED`. Se descartaron el 400 o 422 de validación, que mezclan un error del protocolo con un dato inválido del formulario, y aceptar la petición sin protección.
- Implementado el 26/11/2026 en E3.10 con `App\Shared\Http\Idempotency\EnsureIdempotency`. El middleware abre la transacción más externa de la petición, con tres intentos (ADR-004), y reclama la clave al principio con `INSERT ... ON CONFLICT DO NOTHING` y una respuesta provisional que nadie ve hasta el commit: así la segunda petición con la misma clave espera en el índice único antes de ejecutar nada, y no después de haber competido por el stock. La espera usa un `lock_timeout` propio (`wms.idempotency.lock_timeout_ms`, 5000 ms), que se restablece después del `INSERT` para no limitar los bloqueos de la operación. El hash cubre método, ruta y cuerpo con las claves de los objetos ordenadas. Solo una respuesta 2xx se guarda; cualquier otra revierte la transacción y no deja clave. La respuesta repetida lleva la cabecera `Idempotent-Replayed: true`. La clave queda disponible para la Action con `Idempotency::keyOf()` y se guarda en `inventory_operations.idempotency_key`. Limpieza: `wms:prune-idempotency-keys`, diaria desde `routes/console.php`, borra las claves de más de 7 días (`wms.idempotency.retention_days`); la corre el servicio `scheduler` de docker compose con `schedule:work`.
