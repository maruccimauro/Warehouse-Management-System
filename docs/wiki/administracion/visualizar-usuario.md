# Visualizar usuario (ZW93)

La transacción `ZW93` muestra los datos, el estado, los roles y los depósitos de un usuario sin modificarlos. Exige el permiso [`users.view`](/referencia/permisos#users-view) o [`users.manage`](/referencia/permisos#users-manage) y está en el menú en **Sistema ▸ Usuarios**.

## Pantalla inicial {#inicial}

Ingrese el **Usuario** y presione <kbd>F8</kbd>. La [Lista de usuarios](./lista-de-usuarios) y la pestaña **Usuarios** de [Visualizar rol](./visualizar-rol) abren esta transacción con doble clic sobre un usuario.

### Usuario {#user}

Código del usuario. <kbd>F4</kbd> busca por código, por nombre o por email; en código y nombre, lo escrito se busca como parte del valor. Si el usuario no existe, el sistema informa [`USER_NOT_FOUND`](/referencia/mensajes#user-not-found).

## Ficha {#ficha}

Todos los datos son de solo lectura. <kbd>F3</kbd> vuelve a la pantalla inicial.

<!--@include: ./partes/usuario-campos.md-->
