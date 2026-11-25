# Gestionar parámetros de usuario (SU3)

La transacción `SU3` reúne los datos propios del usuario: el idioma, los parámetros que completan campos de otras pantallas y el cambio de contraseña. Cualquier usuario con sesión la puede abrir; está en el menú en **Sistema ▸ Datos de usuario**.

Los cambios de idioma y de parámetros se aplican al grabar con <kbd>Ctrl</kbd>+<kbd>S</kbd>; se guardan en el servidor y acompañan al usuario en cualquier equipo.

## Datos del usuario {#datos-del-usuario}

Muestra el **Nombre** y el **Correo electrónico** de la cuenta, de solo lectura. Los modifica el administrador.

## Valores fijos {#valores-fijos}

### Idioma {#locale}

Idioma de la interfaz y de los mensajes del sistema. La lista ofrece los idiomas que la interfaz tiene traducidos; hoy, **Español**.

## Parámetros {#parametros}

Un **parámetro de usuario** es un valor que el sistema usa para completar un campo en las pantallas que lo tienen. La tabla muestra el **ID de parámetro**, el **Valor** y su **Descripción**.

### LGN: depósito por defecto {#lgn}

Depósito con el que se completa el campo **Depósito** de las pantallas operativas. Se elige con la [ayuda de búsqueda](./ayuda-de-busqueda#deposito) (<kbd>F4</kbd>), que ofrece solo los depósitos asignados al usuario. Un valor que no existe o que no está asignado no se graba (**LGN: El valor no existe.**).

Si el usuario no tiene depósitos asignados, el campo muestra **Sin depósitos asignados** y no se puede completar. Si el administrador quita después el depósito del parámetro de los asignados al usuario, las pantallas dejan el campo vacío.

El depósito por defecto se ve también a la derecha de la [barra de estado](./pantalla-principal#barra-de-estado).

## Contraseña {#contrasena}

**Cambiar contraseña** abre un diálogo con la **Contraseña actual**, la **Contraseña nueva** y su repetición. La nueva debe tener al menos 12 caracteres y ser distinta de la actual.

::: warning
Cambiar la contraseña cierra las demás sesiones abiertas con la cuenta, por ejemplo en otro equipo. La sesión actual sigue activa.
:::
