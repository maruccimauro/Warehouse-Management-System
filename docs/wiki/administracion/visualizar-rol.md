# Visualizar rol (PFCG)

La transacción `PFCG` muestra los permisos de un rol y los usuarios que lo tienen. Exige el permiso [`users.view`](/referencia/permisos#users-view) o [`users.manage`](/referencia/permisos#users-manage) y está en el menú en **Sistema ▸ Roles**.

Un **rol** reúne los permisos de un puesto de trabajo. Los roles son fijos: no se crean ni se modifican, y se asignan a los usuarios en [Modificar usuario](./modificar-usuario).

## Pantalla inicial {#inicial}

Ingrese el **Rol** y presione <kbd>F8</kbd>.

### Rol {#role}

El rol, por su nombre (**Operador**) o por su identificador (`operator`). <kbd>F4</kbd> ofrece los siete roles: Administrador, Gerente de operaciones, Jefe de operaciones, Supervisor, Operador, Compras y Auditor. Si el rol no existe, el campo lo marca con [`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found).

## Ficha {#ficha}

La ficha muestra el nombre del rol y dos pestañas. Las flechas <kbd>←</kbd> y <kbd>→</kbd> pasan de una pestaña a la otra. <kbd>F3</kbd> vuelve a la pantalla inicial.

### Permisos {#permisos}

Los permisos del rol agrupados por área (depósitos y ubicaciones, catálogo y proveedores, usuarios, inventario, compras y recepción, pedidos y despacho, inventario físico y auditoría), cada uno con su descripción. El nombre de un permiso enlaza a su explicación en la [referencia de permisos](/referencia/permisos) cuando la tiene.

::: info
Un rol incluye también permisos de funciones que el sistema todavía no ofrece. Se muestran con su descripción, sin enlace a la referencia.
:::

### Usuarios {#usuarios}

La [lista](/primeros-pasos/listas) de los usuarios que tienen el rol, activos e inactivos, con las mismas columnas que la [Lista de usuarios](./lista-de-usuarios). Doble clic sobre una fila abre el usuario en [Visualizar usuario](./visualizar-usuario).
