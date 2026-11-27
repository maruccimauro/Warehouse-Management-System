# Reglas de negocio

Una **regla de negocio** es una condición que el sistema controla siempre, en cualquier transacción y para cualquier usuario: si una acción no la cumple, no se realiza y la barra de estado informa el mensaje que corresponde. Esta página reúne las reglas que el sistema aplica hoy, agrupadas por tema, con el motivo de cada una y los mensajes con que se informa. Cada página de transacción explica, además, los controles propios de sus campos.

| Tema | Reglas |
| - | - |
| [Acceso a los depósitos](#acceso) | [Consulta y operación por depósito](#alcance-por-deposito) |
| [Depósitos](#depositos) | [Un depósito inactivo no admite operaciones](#deposito-inactivo) |
| [Datos maestros](#datos-maestros) | [Los datos maestros no se borran](#bajas-logicas), [Jerarquía de categorías](#jerarquia-de-categorias) |
| [Usuarios y seguridad](#usuarios) | [Contraseña inicial](#contrasena-inicial), [Usuario inactivo](#usuario-inactivo), [Siempre hay un administrador](#ultimo-administrador), [Sin escalada de privilegios](#sin-escalada-de-privilegios), [Roles críticos](#roles-criticos) |
| [Controles de inventario](#controles-de-inventario) | [Quién fija los umbrales de ajuste](#umbrales-de-ajuste) |
| [Bandeja de entrada](#bandeja-de-entrada) | [Retención de la bandeja](#retencion-de-la-bandeja) |

## Acceso a los depósitos {#acceso}

### Consulta y operación por depósito {#alcance-por-deposito}

Un usuario opera solo en los depósitos que tiene asignados en su ficha. Con el permiso [`warehouses.operate_all`](./permisos#warehouses-operate-all) opera en todos. Consultar es distinto de operar: con [`warehouses.view_all`](./permisos#warehouses-view-all) el usuario ve los datos de todos los depósitos, para ubicar mercadería en otro, aunque no pueda modificarlos.

**Por qué:** cada depósito tiene su propio personal responsable. Ver los demás ayuda a coordinar; modificarlos no corresponde.

**Mensajes:** un depósito que el usuario no puede ver se informa como inexistente. Una acción sobre un depósito que ve pero en el que no opera se rechaza con [`WAREHOUSE_NOT_ASSIGNED`](./mensajes#warehouse-not-assigned).

## Depósitos {#depositos}

### Un depósito inactivo no admite operaciones {#deposito-inactivo}

Un depósito [inactivo](./estados#warehouse-inactive) no admite operaciones, como bloquear o desbloquear ubicaciones. Se sigue consultando con su historial y admite el mantenimiento de sus zonas y ubicaciones. La reactivación no tiene condiciones.

**Por qué:** desactivar un depósito lo saca de la operación sin perder su historia.

**Mensajes:** [`WAREHOUSE_INACTIVE`](./mensajes#warehouse-inactive).

## Datos maestros {#datos-maestros}

### Los datos maestros no se borran {#bajas-logicas}

Depósitos, usuarios, proveedores y categorías no se eliminan: se desactivan o dejan de usarse, según su [estado](./estados). El registro sigue visible en las consultas.

**Por qué:** los movimientos, las auditorías y los documentos ya registrados los nombran, y tienen que seguir leyéndose igual.

### Jerarquía de categorías {#jerarquia-de-categorias}

Las categorías forman un árbol de hasta 5 niveles, contando la categoría raíz. El nombre de una categoría es único entre las que tienen el mismo padre. Al mover una categoría se mueve con todas sus subcategorías: el padre nuevo no puede ser ella misma ni una de sus subcategorías, y la rama movida no puede pasar del nivel 5.

**Por qué:** un árbol sin ciclos y de profundidad acotada se recorre y se filtra siempre igual.

**Mensajes:** [`CATEGORY_HIERARCHY_CYCLE`](./mensajes#category-hierarchy-cycle), [`CATEGORY_HIERARCHY_TOO_DEEP`](./mensajes#category-hierarchy-too-deep). Un nombre repetido se marca en el campo **Nombre** con [`VALIDATION_FAILED`](./mensajes#validation-failed).

## Usuarios y seguridad {#usuarios}

### Contraseña inicial {#contrasena-inicial}

La contraseña que fija un administrador, al [crear el usuario](/administracion/crear-usuario) o al [restablecerla](/administracion/modificar-usuario#contrasena-inicial), es inicial: hasta cambiarla, el usuario no puede operar. El cambio pide la contraseña actual, no admite repetirla y exige al menos 12 caracteres. Restablecer la contraseña cierra las sesiones abiertas del usuario.

**Por qué:** solo el usuario conoce la contraseña con la que opera; la que conoce el administrador sirve únicamente para el primer ingreso.

**Mensajes:** [`PASSWORD_CHANGE_REQUIRED`](./mensajes#password-change-required).

### Usuario inactivo {#usuario-inactivo}

Un usuario [inactivo](./estados#user-inactive) no puede ingresar, y desactivarlo cierra sus sesiones abiertas. Nadie puede desactivarse a sí mismo.

**Por qué:** quien deja de trabajar en el depósito pierde el acceso en el momento, sin perder su historia.

**Mensajes:** [`USER_INACTIVE`](./mensajes#user-inactive) y [`SELF_DEACTIVATION_NOT_ALLOWED`](./mensajes#self-deactivation-not-allowed).

### Siempre hay un administrador {#ultimo-administrador}

Siempre queda al menos un usuario activo con el rol Administrador. Un cambio de roles o de estado que dejaría el sistema sin ninguno se rechaza, también cuando dos administradores se quitan el rol al mismo tiempo.

**Por qué:** sin un administrador activo nadie podría dar de alta usuarios ni asignar roles.

**Mensajes:** [`LAST_ADMINISTRATOR`](./mensajes#last-administrator).

### Sin escalada de privilegios {#sin-escalada-de-privilegios}

Nadie cambia sus propios roles. Solo se asignan o quitan roles cuyos permisos tiene quien hace el cambio, y solo se administra a un usuario cuyos permisos tiene también quien lo administra.

**Por qué:** un usuario no puede darse ni dar a otro más acceso del que tiene. Restablecer la contraseña de alguien con más permisos equivaldría a tomar su cuenta.

**Mensajes:** [`FORBIDDEN`](./mensajes#forbidden).

### Roles críticos {#roles-criticos}

Los roles **Administrador**, **Gerente de operaciones** y **Jefe de operaciones** son críticos: administran el sistema o controlan la operación por encima de los supervisores. Asignarlos a un usuario que no los tenía exige escribir una frase de confirmación al grabar la ficha, y cada asignación deja un aviso en la [bandeja de entrada](/primeros-pasos/bandeja-de-entrada#avisos) de todos los administradores activos, incluido quien la hizo.

**Por qué:** un rol crítico da mucho acceso de una vez; la frase evita asignarlo por error y el aviso permite que los demás administradores controlen la asignación.

**Mensajes:** [`CRITICAL_ROLE_CONFIRMATION_REQUIRED`](./mensajes#critical-role-confirmation-required).

## Controles de inventario {#controles-de-inventario}

### Quién fija los umbrales de ajuste {#umbrales-de-ajuste}

Los [umbrales de ajuste](/administracion/umbrales-de-ajuste) de un artículo en un depósito los fija solo quien tiene [`inventory.thresholds.manage`](./permisos#inventory-thresholds-manage), en los depósitos donde opera. Quien ajusta stock no fija su propio umbral. Cada cambio de umbral queda auditado.

**Por qué:** el umbral es el control sobre los ajustes; quien opera no fija el control de su propia operación.

**Mensajes:** [`FORBIDDEN`](./mensajes#forbidden).

## Bandeja de entrada {#bandeja-de-entrada}

### Retención de la bandeja {#retencion-de-la-bandeja}

Cada usuario ve y marca solo los avisos de su [bandeja de entrada](/primeros-pasos/bandeja-de-entrada). Archivar un aviso lo marca leído. Los avisos archivados se borran a los 90 días de archivados; los de la carpeta **Entrada** se conservan hasta que el usuario los archiva.

**Por qué:** un aviso archivado ya fue atendido, y la bandeja no es el registro de auditoría: los cambios que originan los avisos quedan auditados aparte.

**Mensajes:** [`VALIDATION_FAILED`](./mensajes#validation-failed) si la marca incluye un aviso que no es del usuario.
