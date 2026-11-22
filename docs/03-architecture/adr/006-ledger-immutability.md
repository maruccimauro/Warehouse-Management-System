# ADR-006: Inmutabilidad del libro garantizada por la base de datos

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-002

## Contexto

Los movimientos confirmados no deben modificarse ni borrarse: las correcciones se hacen con operaciones compensatorias. Si la regla existe solo en el código, una consulta directa o un error de programación puede violarla.

## Decisión

Un trigger `BEFORE UPDATE OR DELETE` en `inventory_operations`, `stock_movements` y `audit_logs` lanza una excepción. La corrección de un error se hace con una operación `reversal` que referencia a la original mediante `reverses_operation_id UNIQUE`, lo que impide revertir dos veces.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Regla solo en la aplicación | Se puede saltear |
| Revocar `UPDATE` y `DELETE` al usuario de la aplicación | Válido y complementario, pero depende de la configuración de cada entorno; el trigger viaja con las migraciones |

## Consecuencias

- Inmutabilidad verificable con una prueba que intenta modificar un movimiento.
- Las pruebas limpian estas tablas con `TRUNCATE`, que el trigger de fila no intercepta.
- Los datos erróneos permanecen en el historial junto con su corrección, que es el comportamiento buscado.
