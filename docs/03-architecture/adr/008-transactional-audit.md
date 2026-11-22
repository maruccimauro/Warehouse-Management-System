# ADR-008: Auditoría explícita dentro de la transacción

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-006

## Contexto

Las operaciones críticas deben quedar auditadas con usuario, momento, entidad afectada, valores anteriores y posteriores y documento origen. La auditoría no puede registrar operaciones que luego se revirtieron ni omitir cambios.

## Decisión

Las Actions escriben en `audit_logs` mediante un servicio `AuditLogger`, dentro de la misma transacción del cambio. Cada registro incluye `request_id` para correlacionarlo con los logs de la aplicación. Para datos maestros de baja criticidad se admite un observer genérico.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Observers de Eloquent para todo | No capturan `increment` ni actualizaciones por query builder, que son justamente las del motor de stock |
| Paquete de auditoría de terceros | Mismo problema de captura y menos control sobre el contenido semántico del registro |
| Auditoría asíncrona por cola | Puede registrar operaciones revertidas o perder registros |

## Consecuencias

- Auditoría consistente con el estado de la base en todo momento.
- Cada Action es responsable de auditar; se verifica con pruebas que toda operación confirmada tenga registro.
