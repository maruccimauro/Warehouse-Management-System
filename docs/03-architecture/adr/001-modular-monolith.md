# ADR-001: Monolito modular

- Estado: Aceptado
- Fecha: 22/11/2026
- Aceptado: 23/11/2026, en la revisión de E0.09

## Contexto

El WMS ejecuta operaciones que modifican varias entidades a la vez (recepción que actualiza orden de compra y stock, picking que mueve stock y consume reservas). Estas operaciones deben ser atómicas. El proyecto lo desarrolla una sola persona.

## Decisión

Una única aplicación Laravel organizada en módulos por contexto de dominio (`Identity`, `Warehouse`, `Catalog`, `Inventory`, `Procurement`, `Receiving`, `Fulfillment`, `Stocktaking`, `Reporting`, `Audit`) bajo `app/Modules`, con una base PostgreSQL compartida. Cada módulo registra sus rutas, policies y migraciones en su propio ServiceProvider. Solo `Inventory` escribe sobre las tablas de stock; los demás módulos lo invocan a través de sus Actions.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Microservicios | Las operaciones entre contextos requerirían sagas y consistencia eventual, sin beneficio para el volumen ni el equipo del proyecto |
| Estructura por defecto de Laravel (`app/Models`, `app/Http/Controllers`) | No expresa los límites del dominio y favorece la lógica en controladores y modelos |

## Consecuencias

- Transacciones ACID locales para todas las operaciones de negocio.
- Los límites entre módulos se mantienen por convención y pruebas de arquitectura, no por la red; requiere disciplina.
- Si un módulo necesitara escalar por separado, sus límites ya están definidos para extraerlo.
