# Modificar producto (MM02)

La transacción `MM02` cambia los datos de un producto, su estado y su stock mínimo en un depósito. Exige el permiso [`catalog.manage`](/referencia/permisos#catalog-manage) y está en el menú en **Logística ▸ Productos**.

## Modificar un producto {#modificar}

1. Ingrese el **SKU** y, si quiere ver o cambiar el stock mínimo, el **Depósito (stock mínimo)**. Presione <kbd>F8</kbd>. Si el producto no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found).
2. Cambie los datos necesarios. El SKU y la unidad base no se modifican.
3. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Producto … grabado.** y vuelve a la pantalla inicial.

::: warning
Discontinuar o bloquear un producto limita lo que se puede hacer con él a partir de ese momento. Antes de cambiar el estado, revise su efecto en la [referencia de estados](/referencia/estados#product).
:::

Los proveedores del producto se mantienen en [Gestionar productos del proveedor](/compras/productos-del-proveedor).

<!--@include: ./partes/producto-campos.md-->
