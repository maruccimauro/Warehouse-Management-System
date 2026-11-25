# Crear artículo del proveedor (ZW61)

La transacción `ZW61` vincula un artículo del catálogo a un proveedor, con el código con que el proveedor lo identifica y su plazo de entrega. Exige el permiso [`suppliers.manage`](/referencia/permisos#suppliers-manage) y está en el menú en **Compras ▸ Proveedores**.

## Pantalla inicial {#inicial}

Ingrese el **Proveedor** y presione <kbd>F8</kbd>.

### Proveedor {#supplier}

[Número](./visualizar-proveedor#number) del proveedor, con o sin los ceros a la izquierda: `12` y `000012` son el mismo. Si no lo conoce, <kbd>F4</kbd> busca por número, por fragmento del nombre o por el identificador fiscal completo. Si no existe, la barra de estado lo informa con [`SUPPLIER_NOT_FOUND`](/referencia/mensajes#supplier-not-found).

## Ficha {#ficha}

Elija el **Artículo**, complete si quiere el **Código del proveedor** y el **Plazo de entrega (días)**, y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Artículo … vinculado al proveedor.** y vuelve a la pantalla inicial.

<!--@include: ./partes/articulo-del-proveedor-campos.md-->
