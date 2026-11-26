# Modificar stock mínimo (ZW42)

La transacción `ZW42` cambia el stock mínimo de un artículo en un depósito. Exige el permiso [`catalog.min_stock.manage`](/referencia/permisos#catalog-min-stock-manage) y está en el menú en **Logística ▸ Inventario**. Para ver o cambiar varios artículos de un depósito, use [Gestionar stock mínimo](./stock-minimo).

## Pantalla inicial {#inicial}

Ingrese el **Depósito** y el **Artículo** y presione <kbd>F8</kbd>. Si el artículo no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found). El depósito debe ser uno donde el usuario opera.

## Ficha {#ficha}

Cambie el **Stock mínimo** y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Stock mínimo del artículo … en … grabado.** y vuelve a la pantalla inicial. <kbd>F3</kbd> vuelve sin grabar, con confirmación si hay datos sin grabar.

<!--@include: ./partes/stock-minimo-campos.md-->
