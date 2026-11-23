# Warehouse Management System

[English](#english) | [Español](#español)

## English

A warehouse management system focused on **inventory transactional consistency**: no negative stock, no duplicated operations and no race conditions between operators working on the same items.

> **Status:** technical foundations stage. The specification and architecture decisions are complete; the runnable skeleton is being built. See the [roadmap](#roadmap).

### What it covers

- Warehouses, zones and physical locations.
- Product catalog and suppliers.
- Inventory per location with an immutable movement ledger.
- Purchase orders and goods receipts, including partial receipts and discrepancies.
- Customer orders with reservations, picking, packing and dispatch.
- Physical stocktaking with approval workflow and auditable adjustments.
- Role and warehouse based permissions, and a full audit trail.

### Engineering highlights

| Problem | Approach |
| - | - |
| Two operators withdrawing the same stock | Pessimistic row locking with deterministic lock ordering |
| Retried requests duplicating operations | `Idempotency-Key` header plus database uniqueness constraints |
| Partial transfers | Single ACID transaction per business operation |
| History tampering | Append-only ledger enforced by database triggers |
| Stock and history drifting apart | Signed delta movements and an automated reconciliation check |

Every decision is documented as an [Architecture Decision Record](docs/03-architecture/adr/README.md) (in Spanish).

### Stack

Laravel (PHP), PostgreSQL, React with TypeScript, Docker. Redis is added only when a concrete need appears.

## Español

Sistema de gestión de depósitos centrado en la **consistencia transaccional del inventario**: sin stock negativo, sin operaciones duplicadas y sin condiciones de carrera entre operadores que trabajan sobre los mismos productos.

> **Estado:** etapa de fundamentos técnicos. La especificación y las decisiones de arquitectura están cerradas; se construye el esqueleto ejecutable. Ver la [hoja de ruta](#roadmap).

### Alcance

- Depósitos, zonas y ubicaciones físicas.
- Catálogo de productos y proveedores.
- Inventario por ubicación con libro de movimientos inmutable.
- Órdenes de compra y recepciones, incluidas recepciones parciales y diferencias.
- Pedidos con reservas, picking, packing y despacho.
- Inventario físico con aprobación y ajustes auditables.
- Permisos por rol y por depósito, y auditoría completa.

### Decisiones técnicas destacadas

| Problema | Solución |
| - | - |
| Dos operadores retiran el mismo stock | Bloqueo pesimista de filas con orden determinístico |
| Reintentos que duplican operaciones | Cabecera `Idempotency-Key` y restricciones de unicidad en la base |
| Transferencias incompletas | Una transacción ACID por operación de negocio |
| Alteración del historial | Libro de solo inserción garantizado por triggers |
| Desfase entre stock e historial | Movimientos con delta firmado y conciliación automatizada |

Cada decisión está documentada como [ADR](docs/03-architecture/adr/README.md).

### Stack

Laravel (PHP), PostgreSQL, React con TypeScript, Docker. Redis se incorpora solo ante una necesidad concreta.

## Roadmap

| Stage | Scope | Status |
| - | - | - |
| E0 | Specification and decisions | Done |
| E1 | Technical foundations, auth, CI | In progress |
| E2 | Warehouses, locations, catalog, users | Pending |
| E3 | Transactional inventory core | Pending |
| E4 | Procurement and receiving | Pending |
| E5 | Orders, picking, packing, dispatch (**MVP 1.0**) | Pending |
| E6 | Stocktaking and reconciliation | Pending |
| E7 | Reports and alerts | Pending |
| E8 | Quality, security and deployment | Pending |

## License

[MIT](LICENSE)
