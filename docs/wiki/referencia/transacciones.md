# Transacciones

Cada pantalla del sistema es una transacción con un código, que la abre desde el [campo de comando](/primeros-pasos/pantalla-principal#campo-de-comando).

| Código | Título | Carpeta del menú | Permiso | Página |
| - | - | - | - | - |
| `ZW01` | Depósitos | Logística ▸ Estructura del depósito | [`warehouses.manage`](./permisos#warehouses-manage) | [Depósitos](/administracion/depositos) |
| `ZW02` | Zonas | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Zonas](/administracion/zonas) |
| `LS01N` | Crear ubicación | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Crear ubicación](/administracion/crear-ubicacion) |
| `LS02N` | Modificar ubicación | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Modificar ubicación](/administracion/modificar-ubicacion) |
| `LS03N` | Visualizar ubicación | Logística ▸ Estructura del depósito | Ninguno: cualquier usuario con sesión | [Visualizar ubicación](/administracion/visualizar-ubicacion) |
| `LS10` | Generar ubicaciones | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Generar ubicaciones](/administracion/generar-ubicaciones) |
| `LX03` | Lista de ubicaciones | Logística ▸ Estructura del depósito | Ninguno: cualquier usuario con sesión | [Lista de ubicaciones](/administracion/lista-de-ubicaciones) |
| `ZW05` | Bloquear o desbloquear ubicación | Logística ▸ Estructura del depósito | [`locations.block`](./permisos#locations-block) | [Bloqueo de ubicaciones](/supervisor/bloqueo-de-ubicaciones) |
| `MM01` | Crear producto | Logística ▸ Catálogo | [`catalog.manage`](./permisos#catalog-manage) | [Crear producto](/administracion/crear-producto) |
| `MM02` | Modificar producto | Logística ▸ Catálogo | [`catalog.manage`](./permisos#catalog-manage) | [Modificar producto](/administracion/modificar-producto) |
| `MM03` | Visualizar producto | Logística ▸ Catálogo | Ninguno: cualquier usuario con sesión | [Visualizar producto](/administracion/visualizar-producto) |
| `MM60` | Lista de productos | Logística ▸ Catálogo | Ninguno: cualquier usuario con sesión | [Lista de productos](/administracion/lista-de-productos) |
| `XK01` | Crear proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Crear proveedor](/compras/crear-proveedor) |
| `XK02` | Modificar proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Modificar proveedor](/compras/modificar-proveedor) |
| `XK03` | Visualizar proveedor | Compras ▸ Proveedores | Ninguno: cualquier usuario con sesión | [Visualizar proveedor](/compras/visualizar-proveedor) |
| `ZW06` | Productos del proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Productos del proveedor](/compras/productos-del-proveedor) |
| `SU3` | Parámetros de usuario | Sistema ▸ Datos de usuario | Ninguno: cualquier usuario con sesión | [Parámetros de usuario](/primeros-pasos/parametros-de-usuario) |

