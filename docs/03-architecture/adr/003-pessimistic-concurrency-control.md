# ADR-003: Control de concurrencia pesimista con orden determinístico de bloqueo

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-002, ADR-004, ADR-013

## Contexto

Varios operadores modifican las mismas filas de stock al mismo tiempo (retiros, transferencias, reservas). Sin control, dos lecturas simultáneas de 10 unidades permiten retirar 16. Las transferencias cruzadas (A hacia B y B hacia A) pueden provocar deadlocks.

## Decisión

- Toda transacción bloquea en un orden global único: 0) fila del depósito (`FOR SHARE` en toda operación; `FOR NO KEY UPDATE` al desactivarlo, REG-015); 1) cabecera del documento raíz (`FOR UPDATE`); 2) documentos dependientes y líneas, por `id` ascendente; 3) ubicaciones involucradas, por `id` (`FOR SHARE` para mover, `FOR UPDATE` para cambiar su estado); 4) filas de `stocks` afectadas (`FOR UPDATE`), en una sola consulta y por `id` ascendente; 5) en una reversión, las líneas originales del libro, que bloquea el trigger `stock_movements_validate_reversal` al insertar cada línea de reversión (ADR-013). Por eso las líneas de una operación se insertan en orden ascendente de `id` de la línea original.
- Las ubicaciones se bloquean antes que las existencias. Con el orden inverso, la aprobación de un inventario físico (que bloquea ubicaciones y luego existencias) y un movimiento concurrente sobre esas ubicaciones forman un ciclo de espera; con un único orden jerárquico el ciclo no puede formarse.
- La disponibilidad se valida después de obtener el bloqueo, nunca antes.
- Las restricciones `CHECK` de la tabla actúan como última barrera.
- Las colas de trabajo, como la de picking, toman el siguiente elemento con `FOR UPDATE SKIP LOCKED`: dos operadores no esperan ni compiten por la misma tarea.

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
- Enmendado el 23/11/2026 en E0.09: nivel 5 del orden por el bloqueo de la línea original en las reversiones parciales y uso de `SKIP LOCKED` en colas de trabajo.
- Enmendado el 23/11/2026 con el ABM de depósitos: nivel 0 por REG-015. Sin él, una desactivación y un movimiento simultáneos verían el depósito activo y dejarían stock en un depósito inactivo. `FOR SHARE` no se excluye entre operaciones, que siguen en paralelo; `FOR NO KEY UPDATE` se excluye con `FOR SHARE` pero no con el `FOR KEY SHARE` de las claves foráneas, de modo que la desactivación no frena las altas de zonas y ubicaciones.
- Enmendado el 23/11/2026 con el catálogo (E2.05). Estado del producto (REG-017): las operaciones leen el producto con `FOR SHARE` y `UpdateProduct` lo cambia con `FOR NO KEY UPDATE`, con la misma semántica que el nivel 0. No ocupa un nivel del orden, porque `FOR SHARE` es compatible consigo mismo y quien toma el bloqueo excluyente no tiene otro bloqueo tomado. Jerarquía de categorías (REG-016): un recorrido del árbol no detecta el ciclo que forman dos movimientos simultáneos, y no hay una fila que bloquear para el árbol completo, así que los cambios de estructura toman el bloqueo consultivo de transacción `pg_advisory_xact_lock(205001)`. Es el primer bloqueo consultivo del sistema; las claves se registran en esta lista para que no se repitan: `205001`, árbol de categorías.
- Enmendado el 24/11/2026 con los proveedores (E2.06). La orden de compra lee el proveedor (REG-018) y el vínculo producto proveedor (REG-019) con `FOR SHARE`; `UpdateSupplier` toma el proveedor con `FOR NO KEY UPDATE`, y el alta, la edición y la baja del vínculo toman su fila en modo excluyente con una sola sentencia. Sigue el criterio del estado del producto: no ocupa un nivel del orden.
