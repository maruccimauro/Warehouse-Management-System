# Gestionar zonas (ZW02)

La transacción `ZW02` mantiene las zonas de un depósito. Exige el permiso [`locations.manage`](/referencia/permisos#locations-manage) y está en el menú en **Logística ▸ Estructura del depósito**.

Una **zona** es un área del depósito con una función, por ejemplo almacenamiento o cuarentena. Cada ubicación pertenece a una zona.

Cada acción tiene además su transacción propia: [Crear zona](./crear-zona) (`ZW21`), [Modificar zona](./modificar-zona) (`ZW22`) y [Visualizar zona](./visualizar-zona) (`ZW23`).

## Elegir el depósito {#seleccion}

Ingrese el **Depósito** y ejecute con <kbd>F8</kbd>.

### Depósito {#warehouse}

Depósito cuyas zonas se mantienen. Se elige con la [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#deposito) (<kbd>F4</kbd>), entre los asignados al usuario, y se completa con el parámetro [`LGN`](/primeros-pasos/parametros-de-usuario#lgn).

## Lista de zonas {#lista}

La [lista](/primeros-pasos/listas) muestra el código, el nombre, el tipo y si la zona es de sistema. Doble clic o <kbd>Enter</kbd> abre la ficha en modo visualizar; **Crear** abre una ficha vacía. <kbd>F3</kbd> vuelve a elegir el depósito.

## Ficha de la zona {#ficha}

En modo visualizar, **Modificar** habilita los cambios. Se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd> y <kbd>F3</kbd> vuelve a la lista, con confirmación si hay datos sin grabar.

Las **zonas de sistema** (recepción, empaque y despacho) las crea el depósito al darse de alta. Se les puede cambiar el nombre, pero no el tipo, y no admiten ubicaciones dadas de alta a mano.

<!--@include: ./partes/zona-campos.md-->
