# ADR-013: Reversión parcial por posición, con límite garantizado por la base

- Estado: Propuesto
- Fecha: 23/11/2026
- Relacionados: ADR-002, ADR-003, ADR-006, ADR-007, ADR-014, ADR-015

## Contexto

En un depósito los errores suelen ser de cantidad, no de documento completo: se registraron 50 unidades y llegaron 40, se despacharon 10 y el cliente devolvió 3. La versión inicial de ADR-006 admitía una sola reversión total por operación (`reverses_operation_id UNIQUE`), lo que obligaba a revertir todo y volver a cargar el documento para corregir una cantidad. SAP resuelve este caso con movimientos de reversión por posición (102 sobre un 101, 602 sobre un 601) que se contabilizan tantas veces como haga falta hasta agotar la cantidad original.

## Decisión

- Toda reversión es una operación `reversal` propia, con usuario, fecha y motivo obligatorio, que referencia a la original con `reverses_operation_id`, sin unicidad.
- Se revierte por posición y por cantidad, de forma repetible hasta agotar el saldo de cada posición. Revertir un documento completo es revertir todas sus posiciones por su saldo.
- Cada línea de reversión apunta a la línea original con `stock_movements.reverses_movement_id`. El trigger `stock_movements_validate_reversal` garantiza en la base que esté sobre la misma existencia, con signo opuesto y con el código de reversión de la original (ADR-014), que la original no sea a su vez una reversión y que lo revertido acumulado no supere su cantidad (`REVERSAL_EXCEEDS_REMAINING`).
- El trigger bloquea la línea original con `FOR UPDATE`, de modo que dos reversiones concurrentes de la misma línea se serializan y la segunda ve lo que revirtió la primera (ADR-003, nivel 5).
- Las operaciones de un documento se revierten desde el documento, que acumula lo revertido por posición; las transferencias y los ajustes, desde el libro. Se revierte en orden inverso de la cadena, aplicado por cantidad.
- La reversión no cambia el estado del documento original (ADR-015).

El detalle por documento está en [domain-model.md](../domain-model.md#reversiones) (DM-04, DM-15 a DM-17).

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Una sola reversión total por operación (`reverses_operation_id UNIQUE`) | Para corregir una cantidad hay que revertir todo y volver a cargar, lo que duplica documentos y oculta el error real |
| Cancelar el documento y crear uno nuevo corregido | Rompe la trazabilidad con la orden o el pedido y exige repetir pasos físicos ya hechos |
| Corregir con ajustes manuales | Pierde el vínculo con el documento que originó el error y descuadra sus cantidades (`quantity_received`, `quantity_picked`) |
| Validar el límite solo en la aplicación | Dos reversiones concurrentes de la misma línea pueden superar el saldo si el código omite un bloqueo; la base lo impide siempre |

## Consecuencias

- Se corrige el error exacto, y el historial muestra qué salió y qué volvió.
- Cada documento expone por posición el original, lo revertido y el neto.
- El límite vive en un trigger, es decir, lógica en la base: se cubre con pruebas de reversión parcial, repetida, excedida y concurrente, ya ejecutadas sobre PostgreSQL 17 durante E0.08.
- Las reversiones repetidas legítimas no tienen unicidad por documento; la protección ante reenvíos es la clave de idempotencia (ADR-007).
