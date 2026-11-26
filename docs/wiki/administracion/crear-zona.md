# Crear zona (ZW21)

La transacción `ZW21` da de alta una zona en un depósito. Exige el permiso [`locations.manage`](/referencia/permisos#locations-manage) y está en el menú en **Logística ▸ Estructura del depósito**.

## Pantalla inicial {#inicial}

Ingrese el **Depósito** y presione <kbd>F8</kbd>.

### Depósito {#warehouse}

Depósito donde se crea la zona, entre los asignados al usuario. Se elige con la [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#deposito) (<kbd>F4</kbd>) y se completa con el parámetro de usuario [`LGN`](/primeros-pasos/parametros-de-usuario#lgn).

## Ficha {#ficha}

Complete **Zona**, **Nombre** y **Tipo** y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. Si el código ya existe en el depósito, el campo lo marca con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists). Si no, el sistema informa **Zona creada.** y vuelve a la pantalla inicial.

<!--@include: ./partes/zona-campos.md-->
