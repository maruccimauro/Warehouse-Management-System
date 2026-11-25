# Gestionar usuarios (SU01)

La transacción `SU01` crea, modifica y visualiza usuarios desde una sola pantalla. Exige el permiso [`users.manage`](/referencia/permisos#users-manage) y está en el menú en **Sistema ▸ Usuarios**.

Un **usuario** es la cuenta con la que una persona ingresa al sistema. Tiene un código, sus datos, una contraseña, los roles que definen qué puede hacer y los depósitos en los que opera. Los usuarios no se eliminan: se desactivan.

Cada acción tiene además su transacción propia: [Crear usuario](./crear-usuario) (`ZW91`), [Modificar usuario](./modificar-usuario) (`ZW92`) y [Visualizar usuario](./visualizar-usuario) (`ZW93`). La [Lista de usuarios](./lista-de-usuarios) (`SUIM`) muestra varios a la vez.

## Pantalla inicial {#inicial}

Ingrese el **Usuario** y elija la acción en la barra de la pantalla:

| Botón | Qué hace |
| - | - |
| **Crear** | Abre una ficha vacía con el código ingresado. Si el código ya existe, el sistema informa [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists); si no cumple el formato, [`VALIDATION_FAILED`](/referencia/mensajes#validation-failed) |
| **Modificar** | Abre la ficha del usuario para cambiarla |
| **Visualizar** | Abre la ficha del usuario en solo lectura |

### Usuario {#user}

Código del usuario. <kbd>F4</kbd> busca por código, por nombre o por email; en código y nombre, lo escrito se busca como parte del valor. Si el usuario no existe, el sistema informa [`USER_NOT_FOUND`](/referencia/mensajes#user-not-found).

## Ficha {#ficha}

En modo visualizar los datos son de solo lectura y **Modificar** habilita el cambio.

En modo crear o modificar se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd>: la ficha vuelve a modo visualizar con lo grabado. <kbd>F3</kbd> vuelve a la pantalla inicial; si hay datos sin grabar, el sistema pide confirmación. Datos, estado, roles, depósitos y contraseña inicial se graban juntos: si algo no se puede grabar, no se graba nada.

En modo modificar, el botón **Asignar contraseña inicial** fija una contraseña nueva, como se explica en [Modificar usuario](./modificar-usuario#contrasena-inicial).

<!--@include: ./partes/usuario-campos.md-->
