# ADR-015: Separación de movimientos, tareas físicas y estados del proceso

- Estado: Propuesto
- Fecha: 23/11/2026
- Relacionados: ADR-002, ADR-013

## Contexto

Las máquinas de estado iniciales mezclaban el paso del trámite con cantidades: la orden de compra tenía `partially_received` y `received`, el pedido tenía `reserved` y `partially_reserved`, y una reversión total de un despacho devolvía el pedido a `packed`. Con reversiones parciales (ADR-013) esa mezcla produce estados que contradicen a las cantidades, como una orden recibida que vuelve a aprobada o un pedido despachado y cancelado a la vez. SAP separa los documentos de material, las tareas de almacén y el estado del documento comercial, y calcula lo recibido o lo entregado a partir de los documentos de material.

## Decisión

El modelo separa tres capas:

| Capa | Qué registra | Cómo se corrige |
| - | - | - |
| Movimientos | La verdad del stock, inmutable | Con otro movimiento, por posición y por cantidad |
| Tareas físicas | El trabajo a ejecutar en el depósito (solicitud de guardado, tarea de picking) | Se ejecutan, se revierten en parte o se confirman con diferencia |
| Estados del proceso | El paso del documento y la acción permitida a continuación | Solo con las transiciones de su máquina |

Reglas:

- Una reversión nunca es una transición de estado: cambia cantidades.
- Lo que se calcula a partir de cantidades no se guarda como estado: lo recibido de una orden de compra, lo reservado o lo faltante de un pedido.
- El sistema no cancela un documento por su cuenta porque sus cantidades quedaron en cero; cancelar es una decisión de un usuario, con motivo.

Las máquinas de estado resultantes están en [domain-model.md](../domain-model.md#principios-movimientos-tareas-y-estados-dm-18) (DM-18).

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Estados derivados de cantidades (`partially_received`, `partially_reserved`) | Cada reversión parcial obliga a recalcular y retroceder estados, y el estado puede contradecir a las cantidades |
| Reversión como transición de estado (un despacho revertido vuelve a `packed`) | Reabre documentos cerrados y admite varios despachos vigentes por pedido |
| Cancelación automática al quedar en cero | Oculta una decisión de negocio detrás de una regla y pierde el motivo |

## Consecuencias

- Máquinas de estado más cortas y estables; la interfaz muestra original, revertido y neto por posición.
- Lo recibido, lo reservado y lo faltante se calculan de las líneas y los movimientos, con los índices correspondientes.
- Una reversión de picking genera una tarea nueva por lo revertido en lugar de reabrir la tarea completada.
