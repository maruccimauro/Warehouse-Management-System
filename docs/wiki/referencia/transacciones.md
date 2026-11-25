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
| `SU3` | Parámetros de usuario | Sistema ▸ Datos de usuario | Ninguno: cualquier usuario con sesión | [Parámetros de usuario](/primeros-pasos/parametros-de-usuario) |

