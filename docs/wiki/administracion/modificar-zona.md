# Modificar zona (ZW22)

La transacción `ZW22` cambia el nombre y el tipo de una zona. Exige el permiso [`locations.manage`](/referencia/permisos#locations-manage) y está en el menú en **Logística ▸ Estructura del depósito**.

## Pantalla inicial {#inicial}

Ingrese el **Depósito** y la **Zona** y presione <kbd>F8</kbd>.

### Depósito {#warehouse}

Depósito de la zona, con <kbd>F4</kbd>. Se completa con el parámetro de usuario [`LGN`](/primeros-pasos/parametros-de-usuario#lgn). Ofrece los depósitos asignados al usuario.

### Zona {#zone}

Código de la zona. <kbd>F4</kbd> ofrece las zonas del depósito elegido; una zona que no existe se marca al confirmar el campo.

## Ficha {#ficha}

Cambie el **Nombre** o el **Tipo** y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Zona grabada.** y vuelve a la pantalla inicial. <kbd>F3</kbd> vuelve sin grabar, con confirmación si hay datos sin grabar.

Las **zonas de sistema** (recepción, empaque y despacho) las crea el depósito al darse de alta. Se les puede cambiar el nombre, pero no el tipo, y no admiten ubicaciones dadas de alta a mano.

<!--@include: ./partes/zona-campos.md-->
