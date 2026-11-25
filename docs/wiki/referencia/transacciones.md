# Transacciones

Cada pantalla del sistema es una transacción con un código, que la abre desde el [campo de comando](/primeros-pasos/pantalla-principal#campo-de-comando).

| Código | Título | Carpeta del menú | Permiso | Página |
| - | - | - | - | - |
| `ZW01` | Gestionar depósitos | Logística ▸ Estructura del depósito | [`warehouses.manage`](./permisos#warehouses-manage) | [Gestionar depósitos](/administracion/depositos) |
| `ZW11` | Crear depósito | Logística ▸ Estructura del depósito | [`warehouses.manage`](./permisos#warehouses-manage) | [Crear depósito](/administracion/crear-deposito) |
| `ZW12` | Modificar depósito | Logística ▸ Estructura del depósito | [`warehouses.manage`](./permisos#warehouses-manage) | [Modificar depósito](/administracion/modificar-deposito) |
| `ZW13` | Visualizar depósito | Logística ▸ Estructura del depósito | Ninguno: cualquier usuario con sesión | [Visualizar depósito](/administracion/visualizar-deposito) |
| `ZW02` | Gestionar zonas | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Gestionar zonas](/administracion/zonas) |
| `ZW21` | Crear zona | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Crear zona](/administracion/crear-zona) |
| `ZW22` | Modificar zona | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Modificar zona](/administracion/modificar-zona) |
| `ZW23` | Visualizar zona | Logística ▸ Estructura del depósito | Ninguno: cualquier usuario con sesión | [Visualizar zona](/administracion/visualizar-zona) |
| `LS01N` | Crear ubicación | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Crear ubicación](/administracion/crear-ubicacion) |
| `LS02N` | Modificar ubicación | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Modificar ubicación](/administracion/modificar-ubicacion) |
| `LS03N` | Visualizar ubicación | Logística ▸ Estructura del depósito | Ninguno: cualquier usuario con sesión | [Visualizar ubicación](/administracion/visualizar-ubicacion) |
| `LS10` | Crear múltiples ubicaciones | Logística ▸ Estructura del depósito | [`locations.manage`](./permisos#locations-manage) | [Crear múltiples ubicaciones](/administracion/crear-multiples-ubicaciones) |
| `LX03` | Lista de ubicaciones | Logística ▸ Estructura del depósito | Ninguno: cualquier usuario con sesión | [Lista de ubicaciones](/administracion/lista-de-ubicaciones) |
| `ZW05` | Bloquear o desbloquear ubicación | Logística ▸ Estructura del depósito | [`locations.block`](./permisos#locations-block) | [Bloqueo de ubicaciones](/supervisor/bloqueo-de-ubicaciones) |
| `MM01` | Crear catálogo | Logística ▸ Catálogo | [`catalog.manage`](./permisos#catalog-manage) | [Crear catálogo](/administracion/crear-catalogo) |
| `MM02` | Modificar catálogo | Logística ▸ Catálogo | [`catalog.manage`](./permisos#catalog-manage) | [Modificar catálogo](/administracion/modificar-catalogo) |
| `MM03` | Visualizar catálogo | Logística ▸ Catálogo | Ninguno: cualquier usuario con sesión | [Visualizar catálogo](/administracion/visualizar-catalogo) |
| `MM60` | Lista del catálogo | Logística ▸ Catálogo | Ninguno: cualquier usuario con sesión | [Lista del catálogo](/administracion/lista-del-catalogo) |
| `XK01` | Crear proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Crear proveedor](/compras/crear-proveedor) |
| `XK02` | Modificar proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Modificar proveedor](/compras/modificar-proveedor) |
| `XK03` | Visualizar proveedor | Compras ▸ Proveedores | Ninguno: cualquier usuario con sesión | [Visualizar proveedor](/compras/visualizar-proveedor) |
| `ZW06` | Gestionar catálogo del proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Gestionar catálogo del proveedor](/compras/catalogo-del-proveedor) |
| `ZW61` | Crear artículo del proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Crear artículo del proveedor](/compras/crear-articulo-del-proveedor) |
| `ZW62` | Modificar artículo del proveedor | Compras ▸ Proveedores | [`suppliers.manage`](./permisos#suppliers-manage) | [Modificar artículo del proveedor](/compras/modificar-articulo-del-proveedor) |
| `ZW63` | Visualizar artículo del proveedor | Compras ▸ Proveedores | Ninguno: cualquier usuario con sesión | [Visualizar artículo del proveedor](/compras/visualizar-articulo-del-proveedor) |
| `SU3` | Gestionar parámetros de usuario | Sistema ▸ Datos de usuario | Ninguno: cualquier usuario con sesión | [Gestionar parámetros de usuario](/primeros-pasos/parametros-de-usuario) |

