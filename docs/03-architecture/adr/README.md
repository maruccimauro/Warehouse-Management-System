# Architecture Decision Records

Decisiones de arquitectura del Warehouse Management System (WMS). Cada ADR documenta el contexto, la decisión, las alternativas descartadas y sus consecuencias.

Estados posibles: Propuesto, Aceptado, Reemplazado por ADR-NNN, Obsoleto.

| ADR | Título | Estado |
| - | - | - |
| [ADR-001](001-modular-monolith.md) | Monolito modular | Aceptado |
| [ADR-002](002-inventory-ledger.md) | Libro de inventario con operaciones y movimientos de delta firmado | Propuesto |
| [ADR-003](003-pessimistic-concurrency-control.md) | Control de concurrencia pesimista con orden determinístico de bloqueo | Propuesto |
| [ADR-004](004-concurrency-retries.md) | Reintentos ante deadlocks y fallas de serialización | Propuesto |
| [ADR-005](005-integer-quantities.md) | Cantidades enteras en la unidad base del producto | Propuesto |
| [ADR-006](006-ledger-immutability.md) | Inmutabilidad del libro garantizada por la base de datos | Propuesto |
| [ADR-007](007-idempotency.md) | Idempotencia en operaciones que modifican stock | Propuesto |
| [ADR-008](008-transactional-audit.md) | Auditoría explícita dentro de la transacción | Propuesto |
| [ADR-009](009-spa-authentication-authorization.md) | Autenticación SPA con Sanctum y autorización por permisos y depósito | Propuesto |
| [ADR-010](010-problem-details-errors.md) | Errores HTTP con Problem Details y códigos de dominio estables | Aceptado |
| [ADR-011](011-redis-deferred.md) | Incorporación diferida de Redis | Propuesto |
| [ADR-012](012-openapi-scramble.md) | Documentación OpenAPI generada con Scramble | Propuesto |
| [ADR-013](013-partial-reversal-by-line.md) | Reversión parcial por posición, con límite garantizado por la base | Propuesto |
| [ADR-014](014-sap-movement-codes.md) | Códigos de movimiento de SAP y rango 9xx para movimientos internos | Propuesto |
| [ADR-015](015-movements-tasks-states.md) | Separación de movimientos, tareas físicas y estados del proceso | Propuesto |
| [ADR-016](016-sap-gui-inspired-interface.md) | Interfaz inspirada en SAP GUI | Propuesto |

Cada ADR pasa a Aceptado en la etapa en que se implementa, ajustado con lo aprendido al implementar. Mientras está Propuesto se enmienda directamente y la enmienda queda anotada en sus consecuencias; una vez Aceptado, un cambio requiere un ADR nuevo que lo reemplace.

Para registrar una decisión nueva, copiar `template.md` con el número siguiente.
