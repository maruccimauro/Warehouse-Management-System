# Estados

El estado de un objeto define qué operaciones admite. Esta página explica cada estado y los cambios posibles entre ellos. Un cambio no permitido se rechaza con [`INVALID_STATE_TRANSITION`](./errores#invalid-state-transition).

## Depósito {#warehouse}

| Desde | Hacia | Quién lo hace |
| - | - | - |
| Activo | Inactivo | Quien tiene [`warehouses.manage`](./permisos#warehouses-manage) |
| Inactivo | Activo | Quien tiene [`warehouses.manage`](./permisos#warehouses-manage) |

Un depósito no se borra: se desactiva. Los dos cambios quedan auditados.

### Activo {#warehouse-active}

Admite todas las operaciones. Un depósito nuevo nace activo, con sus ubicaciones de sistema (RECEPCION, DESPACHO y PACKING) ya creadas.

### Inactivo {#warehouse-inactive}

No admite operaciones, como bloquear o desbloquear ubicaciones ([`WAREHOUSE_INACTIVE`](./errores#warehouse-inactive)). Se consulta con su historial y admite el mantenimiento de zonas y ubicaciones.

## Ubicación {#location}

| Desde | Hacia | Quién lo hace |
| - | - | - |
| Activa | Bloqueada | Quien tiene [`locations.block`](./permisos#locations-block), con un motivo |
| Bloqueada | Activa | Quien tiene [`locations.block`](./permisos#locations-block) |
| Activa | Inactiva | Quien tiene [`locations.manage`](./permisos#locations-manage) |
| Inactiva | Activa | Quien tiene [`locations.manage`](./permisos#locations-manage) |
| Activa | En conteo | El sistema, al iniciar un inventario físico |
| En conteo | Activa | El sistema, al aprobar o cancelar el inventario físico |

Bloqueada y en conteo no se encadenan: no se bloquea una ubicación en conteo ni se cuenta una bloqueada. Una ubicación bloqueada o en conteo tampoco se desactiva; primero vuelve a activa.

### Activa {#location-active}

Admite movimientos de entrada y de salida. Es el único estado que los admite.

### Bloqueada {#location-blocked}

No admite movimientos, por ejemplo por una estantería dañada. El bloqueo exige un motivo y queda auditado. Las ubicaciones de sistema también se pueden bloquear.

### En conteo {#location-counting}

La ubicación está dentro de un inventario físico en curso y no admite movimientos hasta que el inventario se aprueba o se cancela.

### Inactiva {#location-inactive}

La ubicación dejó de usarse. No admite movimientos y no se ofrece para operar. Una ubicación de sistema no se puede desactivar.

## Producto {#product}

Los cambios entre los tres estados son libres y los hace quien tiene [`catalog.manage`](./permisos#catalog-manage); quedan auditados.

### Activo {#product-active}

Admite todas las operaciones.

### Discontinuado {#product-discontinued}

No admite documentos nuevos (órdenes de compra y pedidos), pero deja terminar los abiertos para agotar el stock: la recepción de una orden aprobada, el picking, el despacho, los ajustes y el inventario físico.

### Bloqueado {#product-blocked}

No admite movimientos, salvo el inventario físico y los ajustes, que sirven para corregir.

## Proveedor {#supplier}

Los cambios entre activo e inactivo son libres y los hace quien tiene [`suppliers.manage`](./permisos#suppliers-manage); quedan auditados. Un proveedor no se borra.

### Activo {#supplier-active}

Admite órdenes de compra nuevas.

### Inactivo {#supplier-inactive}

No admite órdenes de compra nuevas y deja terminar las abiertas. Admite el mantenimiento de sus datos y de sus productos.

## Usuario {#user}

El administrador activa y desactiva usuarios con [`users.manage`](./permisos#users-manage). Un usuario no se borra, no puede desactivarse a sí mismo y siempre queda al menos un administrador activo.

### Activo {#user-active}

Puede ingresar al sistema.

### Inactivo {#user-inactive}

No puede ingresar ([`USER_INACTIVE`](./errores#user-inactive)) y sus sesiones abiertas se cierran al desactivarlo.
