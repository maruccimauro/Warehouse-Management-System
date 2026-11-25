# Gestionar productos del proveedor (ZW06)

La transacción `ZW06` mantiene los productos que provee cada proveedor. Exige el permiso [`suppliers.manage`](/referencia/permisos#suppliers-manage) y está en el menú en **Compras ▸ Proveedores**.

El **vínculo** entre un producto y un proveedor indica que ese proveedor lo vende. Una orden de compra a un proveedor solo admite productos vinculados a él. El vínculo guarda además el código con que el proveedor identifica el producto y su plazo de entrega.

## Elegir el proveedor {#seleccion}

Ingrese el **Proveedor** y ejecute con <kbd>F8</kbd>. Si no existe, la barra de estado lo informa con [`SUPPLIER_NOT_FOUND`](/referencia/mensajes#supplier-not-found).

### Proveedor {#supplier}

Identificador fiscal del proveedor, escrito con o sin separadores. Si no lo conoce, <kbd>F4</kbd> busca por fragmento del nombre o por el identificador fiscal completo.

## Lista de productos vinculados {#lista}

La [lista](/primeros-pasos/listas) muestra los productos del proveedor, ordenados por producto, con su nombre, su estado, el código del proveedor y el plazo de entrega.

- **Vincular producto** abre una ficha vacía para agregar uno.
- Doble clic o <kbd>Enter</kbd> sobre una fila abre el vínculo en modo visualizar.
- <kbd>F3</kbd> vuelve a elegir el proveedor.

## Ficha del vínculo {#ficha}

En modo visualizar, **Modificar** habilita los cambios y **Quitar vínculo** lo elimina, previa confirmación. Se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd>, y cada grabación queda hecha en el momento. <kbd>F3</kbd> vuelve a la lista, con confirmación si hay datos sin grabar.

::: warning
Un producto sin vínculo no se puede incluir en órdenes de compra nuevas a ese proveedor. Las órdenes ya aprobadas no se ven afectadas.
:::

Se pueden vincular productos discontinuados o bloqueados y proveedores inactivos: el vínculo es un dato maestro y su uso lo controlan las órdenes de compra.

### Producto {#product}

SKU del producto, con <kbd>F4</kbd> para buscarlo en el catálogo. Se elige al vincular y no cambia después: para reemplazarlo, se quita el vínculo y se crea otro. Si el producto no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found); si ya está vinculado al proveedor, con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists).

### Código del proveedor {#supplier-sku}

Opcional, de hasta 60 caracteres. Código con que el proveedor identifica el producto en sus listas y facturas. Se guarda tal como se escribe, con mayúsculas y minúsculas, y no se repite entre productos del mismo proveedor ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)); otro proveedor puede usar el mismo código.

### Plazo de entrega (días) {#lead-time-days}

Opcional. Días corridos desde el pedido hasta la entrega, de 0 a 365.
