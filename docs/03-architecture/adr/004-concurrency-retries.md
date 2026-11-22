# ADR-004: Reintentos ante deadlocks y fallas de serialización

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-003, ADR-007

## Contexto

Aun con orden de bloqueo determinístico, PostgreSQL puede abortar transacciones por deadlock (SQLSTATE 40P01) o por fallas de serialización (40001), por ejemplo ante interacciones con procesos no previstos.

## Decisión

Las operaciones de inventario se ejecutan con `DB::transaction($callback, $attempts)` con un número acotado de intentos (inicialmente 3). Laravel detecta los errores de concurrencia y vuelve a ejecutar la clausura completa. La clausura no tiene efectos externos (correo, colas, HTTP); esos efectos se despachan después del commit.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Mecanismo propio de reintentos | Duplica lo que el framework ya resuelve |
| Sin reintentos, devolver error al cliente | Traslada al operador un problema transitorio de infraestructura |

## Consecuencias

- Las clausuras transaccionales deben poder reejecutarse sin efectos colaterales.
- Si se agotan los intentos se devuelve 503 con código `CONCURRENCY_CONFLICT`, que el cliente puede reintentar con la misma clave de idempotencia.
