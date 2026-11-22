# ADR-003: Control de concurrencia pesimista con orden determinístico de bloqueo

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-002, ADR-004

## Contexto

Varios operadores modifican las mismas filas de stock al mismo tiempo (retiros, transferencias, reservas). Sin control, dos lecturas simultáneas de 10 unidades permiten retirar 16. Las transferencias cruzadas (A hacia B y B hacia A) pueden provocar deadlocks.

## Decisión

- Toda operación bloquea las filas de `stocks` afectadas con `SELECT ... FOR UPDATE` dentro de la transacción, en una sola consulta y en orden ascendente de `id`.
- Las ubicaciones involucradas se leen con `FOR SHARE` para que un bloqueo de ubicación concurrente no se cruce con el movimiento.
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
- El orden por `id` elimina deadlocks entre operaciones de este motor; los residuales se tratan según ADR-004.
- Contención en filas calientes: límite conocido, a medir con pruebas de carga.
