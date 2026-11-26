# ADR-004: Reintentos ante deadlocks y fallas de serialización

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-003, ADR-007

## Contexto

Aun con orden de bloqueo determinístico, PostgreSQL puede abortar transacciones por deadlock (SQLSTATE 40P01) o por fallas de serialización (40001), por ejemplo ante interacciones con procesos no previstos.

## Decisión

- Las operaciones de inventario se ejecutan con `DB::transaction($callback, $attempts)` con un número acotado de intentos (inicialmente 3). Laravel detecta los errores de concurrencia, revierte y vuelve a ejecutar la clausura completa; también reintenta si el error ocurre al confirmar.
- El reintento solo existe en la transacción más externa: ante un error de concurrencia dentro de una transacción anidada, Laravel no reintenta y lanza `DeadlockException`. Por eso la Action de nivel superior es la única que abre la transacción con intentos; las Actions que invoca participan de ella sin abrir otra.
- La detección por defecto de Laravel reconoce la falla de serialización por SQLSTATE `40001`, pero el deadlock de PostgreSQL (`40P01`) solo por el texto `deadlock detected` del mensaje, que depende de `lc_messages` del servidor. Se registra en el contenedor una implementación propia de `ConcurrencyErrorDetector` que reconoce los SQLSTATE `40001` y `40P01` sin depender del idioma, y delega en la de Laravel para el resto.
- La clausura no tiene efectos externos (correo, colas, HTTP); esos efectos se despachan después del commit (`afterCommit`).

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Mecanismo propio de reintentos | Duplica lo que el framework ya resuelve |
| Sin reintentos, devolver error al cliente | Traslada al operador un problema transitorio de infraestructura |

## Consecuencias

- Las clausuras transaccionales deben poder reejecutarse sin efectos colaterales.
- Los errores que no son de concurrencia, incluidos los de los triggers (`REVERSAL_EXCEEDS_REMAINING`) y el agotamiento de `lock_timeout`, no se reintentan: se traducen según ADR-010.
- Una prueba fuerza un deadlock real entre dos conexiones y verifica que se reintenta; protege el detector propio ante cambios de Laravel.
- Si se agotan los intentos se devuelve 503 con código `CONCURRENCY_CONFLICT`, que el cliente puede reintentar con la misma clave de idempotencia.
- Enmendado el 23/11/2026 en E0.09, tras leer `ManagesTransactions` y `ConcurrencyErrorDetector` de Laravel 12.x: límite del reintento en la transacción externa y detector propio por SQLSTATE.
- Implementado el 26/11/2026 en E3.19. `App\Shared\Database\PostgresConcurrencyErrorDetector` se registra como `ConcurrencyErrorDetector` en `AppServiceProvider`: reconoce `40001` y `40P01` en la excepción o en sus causas, también dentro de la `DeadlockException` de una transacción anidada, y delega el resto en el detector de Laravel. `ProblemDetailsRenderer` usa el mismo detector: un error de concurrencia que llega a la respuesta agotó los intentos y se responde 503 `CONCURRENCY_CONFLICT` (`App\Shared\Exceptions\ConcurrencyConflict`). Pruebas: unitarias con mensajes del servidor en español, y un deadlock real entre dos procesos con bloqueos consultivos cruzados (`tests/Concurrency/DeadlockRetryTest.php`) que se reintenta y, con un solo intento, responde `CONCURRENCY_CONFLICT`. La imagen `postgres:18-alpine` no trae los mensajes traducidos, así que la independencia del idioma la cubren las pruebas unitarias.
- Enmendado el 26/11/2026 en E3.10. En una ruta idempotente la transacción más externa es la del middleware `idempotent`, porque la clave y la respuesta se confirman con la operación (ADR-007); la de la Action queda anidada. Un deadlock en la Action llega al middleware como respuesta con la excepción adjunta, y el middleware la vuelve a lanzar para que su transacción reintente la petición completa. El principio no cambia: reintenta solo la transacción externa.
