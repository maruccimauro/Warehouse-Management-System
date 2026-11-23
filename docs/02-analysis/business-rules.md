# Reglas de negocio e invariantes

Las invariantes son condiciones que el sistema garantiza en todo momento, sin excepción. Cada una tiene al menos un mecanismo de garantía, un código de error estable (ver [ADR-010](../03-architecture/adr/010-problem-details-errors.md)) y una prueba automatizada que la verifica.

## Política de códigos HTTP

| Código | Uso |
| - | - |
| 422 | La petición viola una regla de negocio o de validación con los datos enviados |
| 409 | La petición entra en conflicto con el estado actual del recurso (transición de estado inválida, operación ya confirmada, petición idempotente en curso) |
| 403 | El usuario no tiene el permiso requerido |
| 404 | El recurso no existe o pertenece a un depósito al que el usuario no tiene acceso |
| 503 | Conflicto de concurrencia que persistió tras los reintentos; el cliente puede reintentar con la misma clave de idempotencia |

## Invariantes

### INV-001. Ningún stock puede ser negativo

| Aspecto | Detalle |
| - | - |
| Enunciado | Para toda existencia, el stock físico, el reservado y el bloqueado son mayores o iguales a cero, y reservado más bloqueado no supera el físico |
| Garantía en base de datos | `CHECK` en `stocks` sobre las tres cantidades y sobre su relación |
| Garantía en aplicación | `StockLedger` bloquea las filas afectadas (`FOR UPDATE`) y valida el disponible antes de aplicar deltas negativos ([ADR-003](../03-architecture/adr/003-pessimistic-concurrency-control.md)) |
| Error | 422 `INSUFFICIENT_STOCK` |
| Pruebas | Retiro mayor al disponible. 50 procesos concurrentes retiran 10 unidades de un stock de 100: exactamente 10 éxitos y stock final 0 |
| Etapa | E3 |

### INV-002. Todo cambio de stock genera un movimiento

| Aspecto | Detalle |
| - | - |
| Enunciado | El stock físico de cada existencia es igual a la suma de los deltas de sus movimientos |
| Garantía en aplicación | `StockLedger` es la única clase que escribe en `stocks` y `stock_movements`, en la misma transacción ([ADR-002](../03-architecture/adr/002-inventory-ledger.md)). Prueba de arquitectura que impide escrituras sobre `stocks` desde otras clases |
| Verificación | Comando `wms:reconcile`, ejecutado en CI tras la suite de pruebas |
| Pruebas | Secuencia aleatoria de 500 operaciones válidas e inválidas; la conciliación final no reporta diferencias |
| Etapa | E3 |

### INV-003. Un movimiento confirmado no puede modificarse ni eliminarse

| Aspecto | Detalle |
| - | - |
| Enunciado | Las operaciones, movimientos y registros de auditoría son de solo inserción. Las correcciones se hacen con operaciones de reversión, ejecutadas desde el documento de origen (REG-012) |
| Garantía en base de datos | Trigger `BEFORE UPDATE OR DELETE` que lanza excepción ([ADR-006](../03-architecture/adr/006-ledger-immutability.md)) |
| Garantía en aplicación | Los modelos no exponen operaciones de edición ni borrado |
| Pruebas | `UPDATE` y `DELETE` directos sobre `stock_movements` fallan |
| Etapa | E3 |

### INV-004. Una transferencia es atómica

| Aspecto | Detalle |
| - | - |
| Enunciado | El débito en origen y el crédito en destino ocurren juntos o no ocurren |
| Garantía en base de datos | Una única transacción por operación |
| Garantía en aplicación | La transferencia es una operación con dos movimientos; las filas se bloquean en orden ascendente de `id` para evitar deadlocks |
| Errores | 422 `SAME_SOURCE_AND_DESTINATION` si origen y destino coinciden |
| Pruebas | Falla forzada al acreditar el destino: el origen queda intacto y no se persiste la operación. 20 transferencias cruzadas simultáneas entre A y B conservan la suma total |
| Etapa | E3 |

### INV-005. No se puede retirar stock reservado

| Aspecto | Detalle |
| - | - |
| Enunciado | Un retiro solo puede usar stock disponible, salvo que consuma explícitamente una reserva existente (picking, baja por daño en PACKING y despacho del pedido que la creó, y la solicitud de guardado que comprometió el stock de su origen) |
| Garantía en base de datos | `CHECK (quantity_reserved + quantity_blocked <= quantity_on_hand)` |
| Garantía en aplicación | `StockLedger` distingue líneas que consumen reservas de líneas libres |
| Error | 422 `RESERVED_STOCK_NOT_AVAILABLE` |
| Pruebas | Transferencia de stock reservado rechazada. Dos pedidos concurrentes por las últimas 10 unidades nunca reservan más de 10 |
| Etapa | E3, E5 |

### INV-006. Una recepción no supera lo permitido sin autorización

| Aspecto | Detalle |
| - | - |
| Enunciado | Lo recibido acumulado por línea de orden no supera lo pedido más la tolerancia, salvo con el permiso `receiving.over_receive` |
| Garantía en aplicación | La confirmación bloquea las líneas de la orden (`FOR UPDATE`) antes de validar el acumulado |
| Error | 422 `OVER_RECEIPT_NOT_ALLOWED` |
| Pruebas | Dentro de la tolerancia: aceptada. Por encima sin permiso: rechazada. Por encima con permiso: aceptada y auditada. Dos recepciones parciales concurrentes no superan la tolerancia en conjunto |
| Etapa | E4 |

### INV-007. Una operación no puede confirmarse dos veces

| Aspecto | Detalle |
| - | - |
| Enunciado | Reintentos de una misma petición o confirmaciones repetidas de un documento no generan operaciones adicionales |
| Garantía en base de datos | `UNIQUE (reference_type, reference_id)` en operaciones: un documento origina como máximo una operación, aunque fallen los controles de la aplicación. `UNIQUE (performed_by, idempotency_key)` en operaciones. Trigger `stock_movements_validate_reversal`: lo revertido de cada línea no supera su cantidad original, aunque lleguen reversiones concurrentes. `operation_id UNIQUE` en los documentos, que solo impide que dos documentos apunten a la misma operación. Índice único parcial de un despacho vigente por pedido |
| Garantía en aplicación | Middleware de idempotencia ([ADR-007](../03-architecture/adr/007-idempotency.md)) |
| Errores | 422 `IDEMPOTENCY_KEY_REUSED` (misma clave, otro contenido). 409 `IDEMPOTENCY_REQUEST_IN_PROGRESS`. 409 `OPERATION_ALREADY_CONFIRMED`. 422 `REVERSAL_EXCEEDS_REMAINING` |
| Pruebas | Misma clave enviada 10 veces en paralelo: una sola operación. Despacho confirmado dos veces: una sola salida. Dos reversiones parciales concurrentes de la misma línea que juntas superan el original: una falla |
| Etapa | E3, E4, E5, E6 |

### INV-008. Todo ajuste requiere motivo

| Aspecto | Detalle |
| - | - |
| Enunciado | Los ajustes de inventario registran un motivo de una lista cerrada: `damage` (551), `loss` (711), `found` (712), `correction` (711 o 712) e `initial_load` (561, carga inicial). Los ajustes negativos por encima de un umbral requieren el permiso `inventory.adjust.approve` |
| Garantía en base de datos | `reason_code` obligatorio para operaciones de tipo ajuste (`CHECK` condicional) |
| Garantía en aplicación | Validación del Form Request y de la Action |
| Errores | 422 de validación sin motivo. 403 si supera el umbral sin permiso |
| Pruebas | Ajuste sin motivo rechazado. Ajuste negativo grande sin permiso rechazado |
| Etapa | E3 |

### INV-009. Una ubicación bloqueada o en conteo no admite movimientos

| Aspecto | Detalle |
| - | - |
| Enunciado | Ningún movimiento puede tener como origen o destino una ubicación en estado distinto de `active` |
| Garantía en aplicación | `StockLedger` lee las ubicaciones con `FOR SHARE` dentro de la transacción y valida su estado |
| Error | 422 `LOCATION_BLOCKED` |
| Pruebas | Transferencia hacia una ubicación bloqueada. Transferencia desde una ubicación en conteo. Bloqueo concurrente con un movimiento: uno de los dos espera al otro |
| Etapa | E2, E3, E6 |

### INV-010. Una diferencia de inventario se aprueba antes de ajustar

| Aspecto | Detalle |
| - | - |
| Enunciado | El conteo no modifica stock. Solo la aprobación de un supervisor, distinto de quien contó, genera los ajustes |
| Garantía en aplicación | Máquina de estados del inventario físico; permiso `stocktake.approve`; validación de segregación de funciones |
| Errores | 409 `INVALID_STATE_TRANSITION`. 403 `SELF_APPROVAL_NOT_ALLOWED` |
| Pruebas | Conteo registrado sin aprobar no cambia el stock. El contador no puede aprobar su propio conteo. La aprobación genera ajustes iguales a contado menos sistema |
| Etapa | E6 |

### INV-011. Un pedido se despacha solo si está preparado

| Aspecto | Detalle |
| - | - |
| Enunciado | El despacho requiere el estado `packed`, con lo empaquetado igual a lo pickeado |
| Garantía en base de datos | Índice único parcial `dispatches_one_confirmed_per_order_unique`: un solo despacho vigente por pedido |
| Garantía en aplicación | Máquina de estados del pedido |
| Error | 409 `ORDER_NOT_READY_FOR_DISPATCH` |
| Pruebas | Despacho sin packing rechazado. Packing con diferencias contra lo pickeado rechazado |
| Etapa | E5 |

### INV-012. Las operaciones críticas quedan auditadas

| Aspecto | Detalle |
| - | - |
| Enunciado | Toda operación de inventario, aprobación, cambio de permisos y bloqueo de ubicación tiene un registro de auditoría en la misma transacción |
| Garantía en base de datos | Trigger de inmutabilidad sobre `audit_logs` |
| Garantía en aplicación | `AuditLogger` invocado por las Actions dentro de la transacción ([ADR-008](../03-architecture/adr/008-transactional-audit.md)) |
| Pruebas | Toda operación confirmada en la suite tiene su registro de auditoría. Una operación revertida por error no deja auditoría |
| Etapa | E3 en adelante |

## Reglas complementarias

Reglas de negocio que no son invariantes del inventario pero se aplican con el mismo rigor.

| ID | Regla | Error | Etapa |
| - | - | - | - |
| REG-001 | Quien crea una orden de compra no puede aprobarla | 403 `SELF_APPROVAL_NOT_ALLOWED` | E4 |
| REG-002 | Una orden de compra solo se edita en estado `draft` | 409 `INVALID_STATE_TRANSITION` | E4 |
| REG-003 | Crear o aprobar una orden de compra no modifica stock; solo la recepción confirmada lo hace | | E4 |
| REG-004 | La cantidad rechazada en una recepción requiere motivo y no ingresa al stock | 422 de validación | E4 |
| REG-005 | Un usuario solo opera sobre los depósitos que tiene asignados, salvo con `warehouses.operate_all`. Consulta los demás si tiene `warehouses.view_all`, como la actividad 03 sobre `WERKS` en SAP, para ubicar mercadería entre depósitos | 404 fuera del alcance de consulta; 403 `FORBIDDEN` al operar en un depósito visible no asignado | E2 |
| REG-006 | Una ubicación no puede pertenecer a dos inventarios físicos activos | 409 `LOCATION_ALREADY_IN_STOCKTAKE` | E6 |
| REG-007 | Un operador tiene como máximo una tarea de picking en curso | 409 `PICKING_TASK_IN_PROGRESS` | E5 |
| REG-008 | El picking exige escanear la ubicación y el producto esperados | 422 `SCAN_MISMATCH` | E5 |
| REG-009 | No se puede cancelar un pedido en estado `packed` o posterior | 409 `INVALID_STATE_TRANSITION` | E5 |
| REG-010 | La cantidad depositada en una ubicación no supera su capacidad máxima, si está definida | 422 `LOCATION_CAPACITY_EXCEEDED` | E3 |
| REG-011 | Una diferencia de conteo por encima del umbral exige reconteo por otro operador antes de aprobar | 409 `RECOUNT_REQUIRED` | E6 |
| REG-012 | Toda operación es reversible, como en SAP: las de un documento solo desde el documento, que corrige su estado y sus cantidades; las transferencias y los ajustes desde el libro. La reversión es por posición y por cantidad, repetible hasta agotar el saldo de cada posición; revertir el documento completo es revertir todas sus posiciones por el saldo. Se revierte en orden inverso de la cadena, aplicado por cantidad, con usuario, motivo y permiso `*.reverse` | 409 `OPERATION_NOT_REVERSIBLE`. 409 `DEPENDENT_DOCUMENTS_NOT_REVERSED`. 422 `REVERSAL_EXCEEDS_REMAINING` | E3 a E6 |
| REG-013 | Un inventario físico no se inicia sobre ubicaciones con reservas activas sin confirmación explícita, porque congelarlas impediría pickear esos pedidos | 409 `LOCATION_HAS_ACTIVE_RESERVATIONS` | E6 |
| REG-014 | Ni una transferencia manual ni un ajuste positivo manual tienen como destino una ubicación de sistema (RECEPCION, PACKING o DESPACHO); solo sus documentos cargan esas ubicaciones. Los ajustes negativos sí se admiten. Se valida en `TransferStock` y `AdjustStock` | 422 `SYSTEM_LOCATION_NOT_ALLOWED` | E3 |

## Catálogo de códigos de error de dominio

| Código | HTTP | Regla |
| - | - | - |
| `INSUFFICIENT_STOCK` | 422 | INV-001 |
| `SAME_SOURCE_AND_DESTINATION` | 422 | INV-004 |
| `RESERVED_STOCK_NOT_AVAILABLE` | 422 | INV-005 |
| `OVER_RECEIPT_NOT_ALLOWED` | 422 | INV-006 |
| `IDEMPOTENCY_KEY_REUSED` | 422 | INV-007 |
| `IDEMPOTENCY_REQUEST_IN_PROGRESS` | 409 | INV-007 |
| `OPERATION_ALREADY_CONFIRMED` | 409 | INV-007 |
| `REVERSAL_EXCEEDS_REMAINING` | 422 | INV-007, REG-012 |
| `OPERATION_NOT_REVERSIBLE` | 409 | REG-012 |
| `DEPENDENT_DOCUMENTS_NOT_REVERSED` | 409 | REG-012 |
| `LOCATION_BLOCKED` | 422 | INV-009 |
| `INVALID_STATE_TRANSITION` | 409 | INV-010, REG-002, REG-009 |
| `SELF_APPROVAL_NOT_ALLOWED` | 403 | INV-010, REG-001 |
| `ORDER_NOT_READY_FOR_DISPATCH` | 409 | INV-011 |
| `LOCATION_ALREADY_IN_STOCKTAKE` | 409 | REG-006 |
| `PICKING_TASK_IN_PROGRESS` | 409 | REG-007 |
| `SCAN_MISMATCH` | 422 | REG-008 |
| `LOCATION_CAPACITY_EXCEEDED` | 422 | REG-010 |
| `RECOUNT_REQUIRED` | 409 | REG-011 |
| `LOCATION_HAS_ACTIVE_RESERVATIONS` | 409 | REG-013 |
| `SYSTEM_LOCATION_NOT_ALLOWED` | 422 | REG-014 |
| `CONCURRENCY_CONFLICT` | 503 | ADR-004 |
| `VALUE_ALREADY_EXISTS` | 422 | Unicidad de códigos de datos maestros (código de depósito, de zona y de ubicación por depósito, email de usuario; SKU, código de barras y CUIT desde E2.05 y E2.06). Respaldo de la validación ante una carrera; `context.field` indica el campo |
| `INVALID_CREDENTIALS` | 422 | ADR-009 |
| `SESSION_REQUIRED` | 400 | ADR-009 |
