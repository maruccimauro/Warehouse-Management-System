# Permisos y roles

Un **permiso** habilita una acción concreta, por ejemplo bloquear ubicaciones. Los permisos no se asignan uno por uno: el administrador asigna **roles**, y cada rol reúne los permisos de un puesto de trabajo. El menú muestra solo las transacciones que los permisos del usuario habilitan, y el servidor vuelve a controlar cada operación ([`FORBIDDEN`](./mensajes#forbidden)).

Esta página lista los permisos que usan las funciones disponibles hoy. Los de recepción, pedidos, picking, despacho e inventario físico se suman con esas funciones.

## Roles {#roles}

| Rol | Puesto |
| - | - |
| Administrador | Configura el sistema y puede operar en todos los depósitos. Tiene todos los permisos |
| Supervisor | Supervisa la operación del depósito: aprueba, revierte y bloquea |
| Operador | Ejecuta las tareas del depósito |
| Compras | Gestiona proveedores y órdenes de compra |
| Auditor | Consulta la información, los usuarios, los roles y la auditoría |

## Depósitos {#depositos}

Cada usuario opera en los depósitos que tiene asignados. Consultar un depósito no asignado exige `warehouses.view_all`; operar en él, `warehouses.operate_all`.

### `warehouses.view_all` {#warehouses-view-all}

Consultar todos los depósitos, con sus zonas, ubicaciones y su información, sin tenerlos asignados. Lo tienen todos los roles, para ubicar mercadería entre depósitos.

### `warehouses.operate_all` {#warehouses-operate-all}

Operar en todos los depósitos sin tenerlos asignados. Solo lo tiene el Administrador.

### `warehouses.manage` {#warehouses-manage}

Crear, modificar, activar y desactivar depósitos. Solo lo tiene el Administrador.

## Ubicaciones {#ubicaciones}

### `locations.manage` {#locations-manage}

Crear, modificar, activar y desactivar zonas y ubicaciones, incluida la creación de múltiples ubicaciones por rango. Solo lo tiene el Administrador.

### `locations.block` {#locations-block}

Bloquear y desbloquear ubicaciones, con un motivo. Lo tienen el Administrador y el Supervisor.

## Catálogo y proveedores {#catalogo}

Consultar el catálogo no exige permiso: alcanza con la sesión.

### `catalog.manage` {#catalog-manage}

Crear y modificar categorías, unidades de medida y artículos, incluido el estado del artículo. Los datos de un artículo en un depósito, como el stock mínimo y los umbrales de aprobación de ajustes, exigen además operar en ese depósito. Solo lo tiene el Administrador.

### `suppliers.manage` {#suppliers-manage}

Crear, modificar, activar y desactivar proveedores, y vincularlos con los artículos que proveen. Lo tienen el Administrador y Compras.

## Usuarios {#usuarios}

### `users.view` {#users-view}

Consultar usuarios, con sus roles y depósitos, y los roles con sus permisos: [Visualizar usuario](/administracion/visualizar-usuario), [Lista de usuarios](/administracion/lista-de-usuarios) y [Visualizar rol](/administracion/visualizar-rol). No permite cambiar nada. Lo tienen el Administrador y el Auditor.

### `users.manage` {#users-manage}

Crear y modificar usuarios, asignar roles, depósitos y contraseñas iniciales, y activar o desactivar cuentas: [Gestionar usuarios](/administracion/usuarios), [Crear usuario](/administracion/crear-usuario) y [Modificar usuario](/administracion/modificar-usuario). Incluye la consulta de usuarios y roles. Solo lo tiene el Administrador.

Nadie modifica sus propios roles, y solo se asignan o quitan roles cuyos permisos tiene quien hace el cambio.
