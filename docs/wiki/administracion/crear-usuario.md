# Crear usuario (ZW91)

La transacción `ZW91` da de alta un usuario. Exige el permiso [`users.manage`](/referencia/permisos#users-manage) y está en el menú en **Sistema ▸ Usuarios**.

## Ficha {#ficha}

La transacción abre una ficha vacía. Complete el **Usuario**, el **Nombre**, el **Email** y la **Contraseña inicial** con su repetición, y marque los **Roles** y los **Depósitos**. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Usuario JPEREZ creado.** y deja una ficha vacía para el alta siguiente.

El usuario nuevo nace activo y debe cambiar la contraseña inicial en su primer ingreso. Si el código o el email ya existen, el sistema lo informa al grabar con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists) en el campo correspondiente.

<!--@include: ./partes/usuario-campos.md-->
