# ADR-008: Auditoría explícita dentro de la transacción

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-006

## Contexto

Las operaciones críticas deben quedar auditadas con usuario, momento, entidad afectada, valores anteriores y posteriores y documento origen. La auditoría no puede registrar operaciones que luego se revirtieron ni omitir cambios.

## Decisión

Las Actions escriben en `audit_logs` mediante un servicio `AuditLogger`, dentro de la misma transacción del cambio. Cada registro incluye `request_id` para correlacionarlo con los logs de la aplicación. Para datos maestros de baja criticidad se admite un observer genérico.

- **Identificador de petición.** Un middleware global, el primero de la cadena, toma `X-Request-Id` cuando es un UUID y si no genera un UUID v7. Lo agrega al contexto de Laravel (`Context`), que lo escribe en cada línea de log y lo propaga a los jobs en cola, y lo devuelve en toda respuesta, también en los errores. Aceptar el valor del cliente permite correlacionar con los registros del proxy; solo afecta la correlación, porque el usuario y la IP se registran aparte. La IP va al contexto oculto: viaja a los jobs, pero no se escribe en los logs.
- **Operación de inventario.** `record()` recibe el id de la operación que generó el cambio como parámetro opcional (`audit_logs.operation_id`): la propia operación cuando la audita el motor, o la que contabiliza un documento desde E4. Deducirlo del tipo de la entidad acoplaría `Audit` con los modelos de `Inventory` y no cubriría la auditoría de una recepción.
- **Fuera de HTTP.** En comandos, seeders y el programador, el usuario, la IP y el identificador quedan nulos.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Observers de Eloquent para todo | No capturan `increment` ni actualizaciones por query builder, que son justamente las del motor de stock |
| Paquete de auditoría de terceros | Mismo problema de captura y menos control sobre el contenido semántico del registro |
| Auditoría asíncrona por cola | Puede registrar operaciones revertidas o perder registros |

## Consecuencias

- Auditoría consistente con el estado de la base en todo momento.
- Cada Action es responsable de auditar; se verifica con pruebas que toda operación confirmada tenga registro: al final de cada prueba con base de datos, antes de revertir su transacción, toda fila de `inventory_operations` debe tener su `inventory_operation.created`. Se prefirió a un trigger de restricción diferido porque, bajo la transacción envolvente de las pruebas, el commit nunca ocurre y el trigger no se ejercitaría; `wms:reconcile` (E3.13) suma la misma verificación sobre los datos reales.
- La IP que se registra es la de `Request::ip()`. Detrás del proxy de borde hay que declarar los proxies confiables, o se registra la IP del proxy (despliegue, E8).
