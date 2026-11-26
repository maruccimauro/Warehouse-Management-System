# Modificar usuario (ZW92)

La transacción `ZW92` cambia los datos, el estado, los roles, los depósitos y la contraseña de un usuario. Exige el permiso [`users.manage`](/referencia/permisos#users-manage) y está en el menú en **Sistema ▸ Usuarios**.

Solo se modifica a un usuario cuyos permisos tiene también quien hace el cambio: de otro modo, el sistema rechaza la grabación con [`FORBIDDEN`](/referencia/mensajes#forbidden).

## Pantalla inicial {#inicial}

Ingrese el **Usuario** y presione <kbd>F8</kbd>.

### Usuario {#user}

Código del usuario. <kbd>F4</kbd> busca por código, por nombre o por email; en código y nombre, lo escrito se busca como parte del valor. Si el usuario no existe, el sistema informa [`USER_NOT_FOUND`](/referencia/mensajes#user-not-found).

## Ficha {#ficha}

Cambie los datos y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Usuario JPEREZ grabado.** y vuelve a la pantalla inicial. El código no se modifica. <kbd>F3</kbd> vuelve sin grabar, con confirmación si hay datos sin grabar.

## Restablecer contraseña {#contrasena-inicial}

Cuando el usuario olvidó su contraseña, o hay que quitarle el acceso con la contraseña actual, asígnele una contraseña inicial nueva en la sección **Restablecer contraseña** de la ficha:

1. Ingrese la **Contraseña inicial** y repítala en **Repetir contraseña**.
2. Grabe la ficha con <kbd>Ctrl</kbd>+<kbd>S</kbd>.

Si deja los dos campos vacíos, la contraseña no cambia. Si las dos contraseñas no coinciden, o la contraseña tiene menos de 12 caracteres, el sistema no graba e informa el campo con [`VALIDATION_FAILED`](/referencia/mensajes#validation-failed).

::: warning
Al grabar se cierran todas las sesiones abiertas del usuario, y en su próximo ingreso debe cambiar la contraseña antes de operar. Mientras haya una contraseña escrita sin grabar, la ficha lo recuerda con un aviso.
:::

<!--@include: ./partes/usuario-campos.md-->
