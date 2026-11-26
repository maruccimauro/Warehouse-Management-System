# Visualizar artículo del proveedor (ZW63)

La transacción `ZW63` muestra el vínculo de un artículo con un proveedor sin modificarlo. Cualquier usuario con sesión la puede abrir y está en el menú en **Compras ▸ Proveedores**.

## Pantalla inicial {#inicial}

Ingrese el **Proveedor** y el **Artículo** y presione <kbd>F8</kbd>. Si el artículo no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found); si existe pero no está vinculado al proveedor, con [`SUPPLIER_PRODUCT_NOT_FOUND`](/referencia/mensajes#supplier-product-not-found).

### Proveedor {#supplier}

[Número](./visualizar-proveedor#number) del proveedor, con o sin los ceros a la izquierda: `12` y `0000000012` son el mismo. Si no lo conoce, <kbd>F4</kbd> busca por número, por fragmento del nombre o por el identificador fiscal completo. Si no existe, la barra de estado lo informa con [`SUPPLIER_NOT_FOUND`](/referencia/mensajes#supplier-not-found).

## Ficha {#ficha}

La ficha muestra el vínculo en solo lectura. <kbd>F3</kbd> vuelve a la pantalla inicial.

<!--@include: ./partes/articulo-del-proveedor-campos.md-->
