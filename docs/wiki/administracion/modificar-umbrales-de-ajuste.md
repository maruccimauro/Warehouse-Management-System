# Modificar umbrales de ajuste (ZW36)

La transacción `ZW36` cambia los umbrales de aprobación de ajustes de un artículo en un depósito. Exige el permiso [`inventory.thresholds.manage`](/referencia/permisos#inventory-thresholds-manage) y está en el menú en **Logística ▸ Inventario**. Para ver o cambiar varios artículos de un depósito, use [Gestionar umbrales de ajuste](./umbrales-de-ajuste).

## Pantalla inicial {#inicial}

Ingrese el **Depósito** y el **Artículo** y presione <kbd>F8</kbd>. Si el artículo no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found).

## Ficha {#ficha}

Cambie los umbrales y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Umbrales de ajuste del artículo … en … grabados.** y vuelve a la pantalla inicial. Para quitar un umbral, deje el campo vacío. <kbd>F3</kbd> vuelve sin grabar, con confirmación si hay datos sin grabar.

<!--@include: ./partes/umbrales-de-ajuste-campos.md-->
