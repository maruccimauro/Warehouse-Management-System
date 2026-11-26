## Datos del usuario {#datos}

### Usuario {#username}

Código del usuario, de hasta 12 letras o números, sin espacios ni signos, por ejemplo `JPEREZ`. Se guarda en mayúsculas y tiene que ser único ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)). Identifica al usuario en las pantallas y en las listas, y no cambia después del alta. Para ingresar al sistema el usuario usa su email, no este código.

### Nombre {#name}

Nombre y apellido, obligatorio, de hasta 120 caracteres.

### Email {#email}

Email con el que el usuario ingresa, obligatorio y único. Se guarda en minúsculas, así `JPerez@empresa.com` y `jperez@empresa.com` son el mismo email. Se puede cambiar: desde entonces el usuario ingresa con el nuevo.

### Estado {#status}

[Activo](/referencia/estados#user-active) o [Inactivo](/referencia/estados#user-inactive). Un usuario inactivo no puede ingresar, y desactivarlo cierra sus sesiones abiertas. Los usuarios no se eliminan: se desactivan. Nadie puede desactivarse a sí mismo ([`SELF_DEACTIVATION_NOT_ALLOWED`](/referencia/mensajes#self-deactivation-not-allowed)) y siempre queda al menos un administrador activo ([`LAST_ADMINISTRATOR`](/referencia/mensajes#last-administrator)). El usuario nuevo nace activo.

## Ingreso {#ingreso}

### Contraseña {#password-status}

Indica si la contraseña del usuario es **Inicial: el usuario debe cambiarla al ingresar** o **Vigente**. Solo lectura; no se muestra en el alta.

### Contraseña inicial {#password}

Contraseña con la que el usuario ingresa la próxima vez, de al menos 12 caracteres. El sistema le pide cambiarla antes de operar. En el alta es obligatoria y está en la sección **Ingreso**. Al modificar está en la sección **Restablecer contraseña** y es opcional: vacía, la contraseña del usuario no cambia.

### Repetir contraseña {#password-repeat}

La misma contraseña inicial, para evitar errores de tipeo. Si no coincide, el campo lo marca con **Las contraseñas no coinciden.**

## Roles y depósitos {#roles-y-depositos}

### Roles {#roles}

Roles asignados, cada uno con su casilla. Un **rol** reúne los permisos de un puesto de trabajo; [Visualizar rol](./visualizar-rol) muestra los permisos de cada uno y la [referencia de permisos](/referencia/permisos) explica qué habilita cada permiso. Los cambios de roles rigen en la siguiente acción del usuario, sin cerrar su sesión.

Nadie cambia sus propios roles, y solo se asignan o quitan roles cuyos permisos tiene quien hace el cambio. Si no se cumple, el sistema rechaza la grabación con [`FORBIDDEN`](/referencia/mensajes#forbidden).

### Depósitos {#warehouses}

Depósitos en los que el usuario opera, con su código y su nombre. Se pueden asignar depósitos inactivos. Un usuario con el permiso [`warehouses.operate_all`](/referencia/permisos#warehouses-operate-all) opera en todos los depósitos aunque no los tenga asignados.
