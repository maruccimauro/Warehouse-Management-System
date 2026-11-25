# Visualizar producto (MM03)

La transacción `MM03` muestra los datos de un producto sin modificarlos. Cualquier usuario con sesión la puede abrir y está en el menú en **Logística ▸ Catálogo**.

## Visualizar un producto {#visualizar}

1. Ingrese el **SKU** y, si quiere ver el stock mínimo de un depósito, el **Depósito**. Presione <kbd>F8</kbd>. Si el producto no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found).
2. La ficha muestra los datos en solo lectura, con el estado y si el producto tiene seguimiento por lote.
3. <kbd>F3</kbd> vuelve a la pantalla inicial.

## Proveedores del producto {#proveedores}

Debajo de la ficha, la [lista](/primeros-pasos/listas) **Proveedores del producto** muestra los proveedores vinculados, con su identificador fiscal, si están activos, el código con que cada uno identifica el producto y su plazo de entrega. Los vínculos se mantienen en [Productos del proveedor](/compras/productos-del-proveedor).

::: tip
En la [Lista de productos](./lista-de-productos), el doble clic sobre una fila abre esta transacción directamente en la ficha de ese producto.
:::

<!--@include: ./partes/producto-campos.md-->
