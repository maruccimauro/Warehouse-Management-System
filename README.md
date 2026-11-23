# Warehouse Management System

[![CI](https://github.com/maruccimauro/Warehouse-Management-System/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/maruccimauro/Warehouse-Management-System/actions/workflows/ci.yml)

[English](#english) | [Español](#español)

## English

A warehouse management system focused on **inventory transactional consistency**: no negative stock, no duplicated operations and no race conditions between operators working on the same items.

> **Status:** technical foundations stage. The specification and architecture decisions are complete; the skeleton runs locally with authentication, the SAP GUI inspired shell and CI. See the [roadmap](#roadmap).

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

### Running locally

Requirements: Git and Docker with Compose v2 (Docker Desktop on Windows and macOS). Nothing else is installed on the host: PHP, Composer, Node and PostgreSQL run in containers.

```bash
git clone https://github.com/maruccimauro/Warehouse-Management-System.git
cd Warehouse-Management-System
docker compose up -d
```

The first start takes a few minutes: it builds the PHP image and installs the backend and frontend dependencies. Every start also applies pending migrations and seeds development data (idempotent). Then open <http://localhost:8080> and sign in with the development administrator: `admin@wms.test` / `admin-dev-1`. Ten fictitious users (`usuario01@wms.test` to `usuario10@wms.test`) are created as well. These credentials are public and only valid in the local environment; the seeders refuse to run outside `local` and `testing`.

| Task | Command |
| - | - |
| Follow the backend start | `docker compose logs -f app` |
| Backend checks (Pint, PHPStan, Pest) | `docker compose exec app composer check` |
| Frontend checks (tsc, ESLint, Vitest) | `docker compose exec frontend npm run check` |
| Stop | `docker compose down` |
| Reset the database | `docker compose down -v` |

Configuration is optional. To change ports or database credentials, copy `.env.example` to `.env` at the repository root before starting. On Linux, set `UID` and `GID` there to the output of `id -u` and `id -g` and rebuild with `docker compose build app`, so generated files belong to your user. Application settings (administrator credentials, number of fictitious users) live in `backend/.env`, created on the first start.

Tests always run against PostgreSQL in a separate `wms_testing` database, never SQLite: constraints and triggers are part of the system under test.

## Español

Sistema de gestión de depósitos centrado en la **consistencia transaccional del inventario**: sin stock negativo, sin operaciones duplicadas y sin condiciones de carrera entre operadores que trabajan sobre los mismos productos.

> **Estado:** etapa de fundamentos técnicos. La especificación y las decisiones de arquitectura están cerradas; el esqueleto corre localmente con autenticación, la interfaz inspirada en SAP GUI y CI. Ver la [hoja de ruta](#roadmap).

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

### Ejecución local

Requisitos: Git y Docker con Compose v2 (Docker Desktop en Windows y macOS). No se instala nada más en el host: PHP, Composer, Node y PostgreSQL corren en contenedores.

```bash
git clone https://github.com/maruccimauro/Warehouse-Management-System.git
cd Warehouse-Management-System
docker compose up -d
```

El primer arranque tarda unos minutos: construye la imagen de PHP e instala las dependencias del backend y del frontend. Cada arranque aplica además las migraciones pendientes y siembra los datos de desarrollo (idempotente). Después, abrir <http://localhost:8080> e ingresar con el administrador de desarrollo: `admin@wms.test` / `admin-dev-1`. También se crean diez usuarios ficticios (`usuario01@wms.test` a `usuario10@wms.test`). Estas credenciales son públicas y solo valen en el entorno local; los seeders se niegan a correr fuera de `local` y `testing`.

| Tarea | Comando |
| - | - |
| Seguir el arranque del backend | `docker compose logs -f app` |
| Verificación del backend (Pint, PHPStan, Pest) | `docker compose exec app composer check` |
| Verificación del frontend (tsc, ESLint, Vitest) | `docker compose exec frontend npm run check` |
| Detener | `docker compose down` |
| Reiniciar la base de datos | `docker compose down -v` |

La configuración es opcional. Para cambiar puertos o credenciales de la base, copiar `.env.example` a `.env` en la raíz antes de arrancar. En Linux, definir ahí `UID` y `GID` con la salida de `id -u` e `id -g` y reconstruir con `docker compose build app`, para que los archivos generados sean del usuario. La configuración de la aplicación (credenciales del administrador, cantidad de usuarios ficticios) vive en `backend/.env`, que se crea en el primer arranque.

Las pruebas corren siempre contra PostgreSQL en una base aparte, `wms_testing`, nunca SQLite: las restricciones y los triggers son parte del sistema bajo prueba.

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
