# ADR-003: Control de concurrencia pesimista con orden determinístico de bloqueo

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-002, ADR-004

## Contexto

Varios operadores modifican las mismas filas de stock al mismo tiempo (retiros, transferencias, reservas). Sin control, dos lecturas simultáneas de 10 unidades permiten retirar 16. Las transferencias cruzadas (A hacia B y B hacia A) pueden provocar deadlocks.

## Decisión

- Toda transacción bloquea en un orden global único: 1) cabecera del documento raíz (`FOR UPDATE`); 2) documentos dependientes y líneas, por `id` ascendente; 3) ubicaciones involucradas, por `id` (`FOR SHARE` para mover, `FOR UPDATE` para cambiar su estado); 4) filas de `stocks` afectadas (`FOR UPDATE`), en una sola consulta y por `id` ascendente.
- Las ubicaciones se bloquean antes que las existencias. Con el orden inverso, la aprobación de un inventario físico (que bloquea ubicaciones y luego existencias) y un movimiento concurrente sobre esas ubicaciones forman un ciclo de espera; con un único orden jerárquico el ciclo no puede formarse.
- La disponibilidad se valida después de obtener el bloqueo, nunca antes.
- Las restricciones `CHECK` de la tabla actúan como última barrera.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Control optimista con columna `version` | En un depósito las filas calientes (muelle de recepción, productos de alta rotación) son la norma; los conflictos y reintentos serían frecuentes |
| Aislamiento `SERIALIZABLE` en toda la aplicación | Correcto pero con más abortos y menor previsibilidad; el bloqueo explícito documenta la intención en el código |
| Bloqueos distribuidos en Redis | Introduce una segunda fuente de coordinación fuera de la transacción de la base |

## Consecuencias

- Ninguna carrera puede producir stock negativo ni actualizaciones perdidas.
- El orden global y el orden por `id` dentro de cada nivel eliminan los deadlocks entre operaciones de este motor y con las transiciones de documentos; los residuales se tratan según ADR-004.
- Corregido el 23/11/2026 durante E0.08: la versión inicial bloqueaba existencias antes que ubicaciones (ver [database-design.md](../database-design.md#concurrencia)).
- Contención en filas calientes: límite conocido, a medir con pruebas de carga.
