# ADR-002: Libro de inventario con operaciones y movimientos de delta firmado

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-003, ADR-006, ADR-013, ADR-014

## Contexto

El inventario necesita consulta rápida del estado actual y reconstrucción completa de la historia. Un esquema con un movimiento por operación y columnas de origen y destino complica la conciliación: una transferencia afecta dos filas de stock con una sola fila de movimiento.

## Decisión

Dos niveles de registro y una proyección:

- `inventory_operations`: cabecera de la operación de negocio (tipo, documento origen, motivo, usuario, clave de idempotencia). Una reversión es una operación propia que referencia a la original con `reverses_operation_id`.
- `stock_movements`: una línea por fila de stock afectada, con `delta` firmado distinto de cero, `balance_after` y un código de movimiento (`movement_code`, ADR-014). Una línea de reversión apunta a la línea original que revierte con `reverses_movement_id` (ADR-013).
- `stocks`: proyección del estado actual por producto, ubicación y lote, actualizada en la misma transacción.

Una transferencia es una operación con dos líneas (931 negativa en origen, 931 positiva en destino). Para toda fila de stock se cumple `quantity_on_hand = SUM(delta)` de sus movimientos, verificado por el comando `wms:reconcile`, que también verifica que `quantity_reserved` y `quantity_blocked` coincidan con las reservas y los bloqueos activos de la fila.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Movimiento único con `source_location_id` y `destination_location_id` | La conciliación por fila de stock requiere interpretar el tipo de movimiento; propenso a errores |
| Solo libro, sin proyección (event sourcing puro) | Cada consulta de existencias exige agregar todo el historial |
| Solo proyección, sin libro | Sin trazabilidad ni posibilidad de reconstrucción |

## Consecuencias

- Conciliación automatizable y verificable en CI.
- Duplicación controlada de datos (proyección y libro) que se mantiene sincronizada con una única puerta de escritura (`StockLedger`).
- La reversión parcial por línea se expresa en el mismo libro, sin tablas aparte: lo revertido de una línea es la suma de sus líneas de reversión.
- Crecimiento continuo de `stock_movements`; mitigado con índices compuestos y particionado por fecha como evolución.
- Enmendado el 23/11/2026 en E0.09: códigos de movimiento, vínculo de reversión por línea y conciliación de reservas y bloqueos, según [database-design.md](../database-design.md#e3-núcleo-transaccional-de-inventario).
