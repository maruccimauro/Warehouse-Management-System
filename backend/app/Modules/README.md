# Módulos

Monolito modular (ADR-001): un módulo por contexto de dominio, con una base PostgreSQL compartida.

| Módulo | Responsabilidad |
| - | - |
| `Identity` | Usuarios, autenticación, roles y permisos, asignación de depósitos |
| `Warehouse` | Depósitos, zonas y ubicaciones |
| `Catalog` | Productos, categorías, unidades de medida y proveedores |
| `Inventory` | Existencias y libro de movimientos |
| `Procurement` | Órdenes de compra |
| `Receiving` | Recepciones y tareas de ubicación (putaway) |
| `Fulfillment` | Pedidos, reservas, picking, packing y despacho |
| `Stocktaking` | Inventario físico, conteos y ajustes |
| `Reporting` | Reportes y consultas de solo lectura |
| `Audit` | Registro de auditoría |

## Estructura de un módulo

Cada directorio se crea cuando el módulo lo necesita.

```
app/Modules/{Nombre}/
├── {Nombre}ServiceProvider.php   extiende App\Shared\Providers\ModuleServiceProvider
├── Actions/                      casos de uso: una clase por operación de negocio
├── Database/Factories/           factories de los modelos del módulo
├── Database/Migrations/          migraciones del módulo, cargadas por el provider
├── Database/Seeders/             datos de desarrollo idempotentes, llamados desde DatabaseSeeder
├── Exceptions/                   excepciones de dominio con código estable (ADR-010)
├── Http/Controllers/
├── Http/Requests/
├── Models/
├── Policies/                     declaradas en $policies del provider
└── Routes/api.php                registrado bajo /api/v1 con el grupo de middleware api
```

## Reglas

- Solo `Inventory` escribe en `stocks` y `stock_movements`. Los demás módulos invocan sus Actions.
- Un módulo usa otro a través de sus Actions y modelos, nunca de sus controladores.
- Lo transversal que no es de dominio (manejo de errores, controlador de health, provider base) va en `app/Shared`.
- Un módulo nuevo se registra en `bootstrap/providers.php`. `tests/Feature/ModuleRegistrationTest.php` verifica que la lista coincida con ADR-001.
- `tests/Architecture/ModuleBoundariesTest.php` hace cumplir estas reglas con las pruebas de arquitectura de Pest: `Shared` no depende de los módulos, ningún módulo usa la capa HTTP de otro y los modelos de `Inventory` solo se usan en `Inventory` y en `Reporting`.
