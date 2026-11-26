# Permisos y roles

Un **permiso** habilita una acción concreta, por ejemplo bloquear ubicaciones. Los permisos no se asignan uno por uno: el administrador asigna **roles**, y cada rol reúne los permisos de un puesto de trabajo. El menú muestra solo las transacciones que los permisos del usuario habilitan, y el servidor vuelve a controlar cada operación ([`FORBIDDEN`](./mensajes#forbidden)).

Esta página lista los permisos que usan las funciones disponibles hoy. Los de recepción, pedidos, picking, despacho e inventario físico se suman con esas funciones.

## Roles {#roles}

| Rol | Puesto |
| - | - |
| Administrador | Configura el sistema y puede operar en todos los depósitos. Tiene todos los permisos |
| Gerente de operaciones | Controla la operación de los depósitos que tiene asignados, por encima de los supervisores: aprueba, fija los umbrales de ajuste y carga el stock inicial, sin la operación diaria ni la administración de usuarios |
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

Crear, modificar, activar y desactivar zonas y ubicaciones, incluida la creación de múltiples ubicaciones por rango, en los depósitos donde el usuario opera. Lo tienen el Administrador y el Supervisor.

### `locations.block` {#locations-block}

Bloquear y desbloquear ubicaciones, con un motivo. Lo tienen el Administrador y el Supervisor.

## Catálogo y proveedores {#catalogo}

Consultar el catálogo no exige permiso: alcanza con la sesión.

### `catalog.manage` {#catalog-manage}

Crear y modificar categorías, unidades de medida y los datos generales de los artículos, incluido su estado. No incluye los datos de un artículo en un depósito, que tienen sus propios permisos. Solo lo tiene el Administrador.

### `catalog.min_stock.manage` {#catalog-min-stock-manage}

Modificar el stock mínimo de un artículo en los depósitos donde el usuario opera: [Gestionar stock mínimo](/supervisor/stock-minimo) y [Modificar stock mínimo](/supervisor/modificar-stock-minimo). Lo tienen el Administrador y el Supervisor.

### `suppliers.manage` {#suppliers-manage}

Crear, modificar, activar y desactivar proveedores, y vincularlos con los artículos que proveen. Lo tienen el Administrador y Compras.

## Inventario {#inventario}

### `inventory.thresholds.manage` {#inventory-thresholds-manage}

Fijar los umbrales de aprobación de ajustes de un artículo en los depósitos donde el usuario opera: [Gestionar umbrales de ajuste](/administracion/umbrales-de-ajuste) y [Modificar umbrales de ajuste](/administracion/modificar-umbrales-de-ajuste). Es un control central: quien ajusta stock no fija su propio umbral. Lo tienen el Administrador y el Gerente de operaciones.

### `inventory.initial_load` {#inventory-initial-load}

Cargar el stock inicial de los artículos en los depósitos donde el usuario opera, con el movimiento [561](./movimientos#mov-561). La carga no pasa por el umbral de aprobación de los ajustes, por eso el permiso queda reservado. Lo tienen el Administrador y el Gerente de operaciones.

## Usuarios {#usuarios}

### `users.view` {#users-view}

Consultar usuarios, con sus roles y depósitos, y los roles con sus permisos: [Visualizar usuario](/administracion/visualizar-usuario), [Lista de usuarios](/administracion/lista-de-usuarios) y [Visualizar rol](/administracion/visualizar-rol). No permite cambiar nada. Lo tienen el Administrador y el Auditor.

### `users.manage` {#users-manage}

Crear y modificar usuarios, asignar roles, depósitos y contraseñas iniciales, y activar o desactivar cuentas: [Gestionar usuarios](/administracion/usuarios), [Crear usuario](/administracion/crear-usuario) y [Modificar usuario](/administracion/modificar-usuario). Incluye la consulta de usuarios y roles. Solo lo tiene el Administrador.

Nadie modifica sus propios roles, y solo se asignan o quitan roles cuyos permisos tiene quien hace el cambio.
