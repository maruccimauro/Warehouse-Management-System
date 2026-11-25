# Modificar unidad de medida (ZW82)

La transacción `ZW82` cambia el nombre de una unidad de medida. Exige el permiso [`catalog.manage`](/referencia/permisos#catalog-manage) y está en el menú en **Logística ▸ Catálogo**.

## Pantalla inicial {#inicial}

Ingrese la **Unidad** y presione <kbd>F8</kbd>.

### Unidad {#unit}

Código de la unidad de medida. <kbd>F4</kbd> ofrece todas las unidades con su nombre. Si el código no existe, el campo lo marca con [`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found).

## Ficha {#ficha}

Cambie el **Nombre** y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Unidad de medida grabada.** y vuelve a la pantalla inicial. El código no se modifica. <kbd>F3</kbd> vuelve sin grabar, con confirmación si hay datos sin grabar.

<!--@include: ./partes/unidad-de-medida-campos.md-->
