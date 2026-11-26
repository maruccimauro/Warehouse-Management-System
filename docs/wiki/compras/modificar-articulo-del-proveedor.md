# Modificar artículo del proveedor (ZW62)

La transacción `ZW62` cambia el código del proveedor y el plazo de entrega de un artículo vinculado, o quita el vínculo. Exige el permiso [`suppliers.manage`](/referencia/permisos#suppliers-manage) y está en el menú en **Compras ▸ Proveedores**.

## Pantalla inicial {#inicial}

Ingrese el **Proveedor** y el **Artículo** y presione <kbd>F8</kbd>. Si el artículo no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found); si existe pero no está vinculado al proveedor, con [`SUPPLIER_PRODUCT_NOT_FOUND`](/referencia/mensajes#supplier-product-not-found).

### Proveedor {#supplier}

[Número](./visualizar-proveedor#number) del proveedor, con o sin los ceros a la izquierda: `12` y `0000000012` son el mismo. Si no lo conoce, <kbd>F4</kbd> busca por número, por fragmento del nombre o por el identificador fiscal completo. Si no existe, la barra de estado lo informa con [`SUPPLIER_NOT_FOUND`](/referencia/mensajes#supplier-not-found).

## Ficha {#ficha}

Cambie el **Código del proveedor** o el **Plazo de entrega (días)** y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Vínculo con el artículo … grabado.** y vuelve a la pantalla inicial.

**Quitar vínculo** elimina el vínculo, después de confirmar, y vuelve a la pantalla inicial.

::: warning
Un artículo sin vínculo no se puede incluir en órdenes de compra nuevas a ese proveedor. Las órdenes ya aprobadas no se ven afectadas.
:::

<!--@include: ./partes/articulo-del-proveedor-campos.md-->
