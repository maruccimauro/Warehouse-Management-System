# ADR-014: Códigos de movimiento de SAP y rango 9xx para movimientos internos

- Estado: Propuesto
- Fecha: 23/11/2026
- Relacionados: ADR-002, ADR-013

## Contexto

El tipo de operación (`receipt`, `transfer`, `pick`) basta para la lógica de la aplicación, pero no distingue dentro de una operación la salida de la entrada ni el movimiento original de su reversión. Quienes trabajan con SAP reconocen los movimientos por su clase (101, 102, 601), y los reportes de movimientos se agrupan por ese código.

## Decisión

- Cada línea de `stock_movements` lleva un `movement_code`. Se usan los códigos de SAP MM-IM donde hay equivalente (101, 102, 551, 552, 561, 562, 601, 602, 701, 702, 711, 712) y el rango 9xx, reservado en SAP para clases propias, para los movimientos internos del depósito: 911 guardado, 921 pick, 931 transferencia y sus reversiones 912, 922 y 932. En SAP esos movimientos internos son tareas de almacén de WM/EWM, sin clase de movimiento de MM-IM.
- El catálogo `movement_types` tiene clave `(code, direction)` y define el código de reversión de cada movimiento. Lo carga la migración, no el seeder de demostración.
- `stock_movements` tiene la columna generada `direction`, calculada del signo de `delta`, y una clave foránea compuesta hacia `movement_types`: la base rechaza un código con el signo equivocado, por ejemplo un 101 con delta negativo.
- La operación conserva su `type`; la correspondencia entre tipo de operación y códigos la valida `StockLedger`.

El catálogo completo y su equivalencia con SAP están en [database-design.md](../database-design.md#e3-núcleo-transaccional-de-inventario) (DM-09).

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Solo el tipo de operación | No distingue la dirección de cada línea ni la reversión de un movimiento original, y no ofrece un vocabulario reconocible |
| Numeración propia sin relación con SAP | Pierde la referencia que identifica de inmediato quien tiene experiencia en SAP |
| Tipos de stock de SAP (libre, bloqueado, calidad) con sus movimientos 343 y 344 | El bloqueo se modela con `stock_blocks`, sin mover stock; los tipos de stock complican la proyección sin necesidad dentro del alcance |

## Consecuencias

- Libro de movimientos y reportes filtrables por código, con el índice `(movement_code, created_at)`.
- El código de reversión de cada línea queda determinado por el catálogo y lo verifica el trigger de ADR-013.
- Las desviaciones respecto de SAP, como el uso de 701 y 702 como reversión una de la otra, están justificadas en el diseño.
- Agregar un movimiento exige una migración del catálogo y su página de ayuda en la wiki (DM-11).
