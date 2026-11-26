# ADR-006: Inmutabilidad del libro garantizada por la base de datos

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-002, ADR-013

## Contexto

Los movimientos confirmados no deben modificarse ni borrarse: las correcciones se hacen con operaciones compensatorias. Si la regla existe solo en el código, una consulta directa o un error de programación puede violarla.

## Decisión

- Un trigger `BEFORE UPDATE OR DELETE` en `inventory_operations`, `stock_movements`, `putaway_task_reversals` y `audit_logs` lanza una excepción.
- La corrección de un error se hace con una operación `reversal` que referencia a la original con `reverses_operation_id`. La reversión es parcial por línea y repetible (ADR-013): cada línea de reversión apunta a la línea original con `reverses_movement_id`, y el trigger `stock_movements_validate_reversal` impide en la base revertir más que la cantidad original de cada línea.
- En producción, la aplicación se conecta con un rol sin propiedad de las tablas ni privilegio `TRUNCATE`; las migraciones corren con el rol dueño del esquema. Es la defensa contra lo que un trigger de fila no intercepta: `TRUNCATE`, `ALTER TABLE ... DISABLE TRIGGER` y `session_replication_role`.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Regla solo en la aplicación | Se puede saltear |
| Solo revocar `UPDATE` y `DELETE` al usuario de la aplicación | Depende de la configuración de cada entorno; el trigger viaja con las migraciones. Se adopta como complemento, no como única defensa |
| `reverses_operation_id UNIQUE`, una sola reversión total por operación | Obliga a revertir todo y volver a cargar para corregir una cantidad; reemplazado por la reversión parcial con límite por línea (ADR-013) |

## Consecuencias

- Inmutabilidad verificable con una prueba que intenta modificar un movimiento.
- Las pruebas limpian estas tablas con `TRUNCATE`, que el trigger de fila no intercepta; en producción el rol de la aplicación no tiene ese privilegio (E8). Las pruebas de concurrencia (E3.14, `tests/Concurrency`) usan `DatabaseTruncation` en lugar de la transacción envolvente, porque los procesos hijos solo ven datos confirmados; conservan `movement_types`, que carga la migración.
- Los datos erróneos permanecen en el historial junto con su corrección, que es el comportamiento buscado.
- Enmendado el 23/11/2026 en E0.09: la unicidad de `reverses_operation_id` se reemplazó por el límite por línea de DM-15, se sumó `putaway_task_reversals` y se incorporó la separación de roles de la base.
