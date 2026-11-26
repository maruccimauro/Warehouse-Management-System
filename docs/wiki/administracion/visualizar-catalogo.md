# Visualizar catálogo (MM03)

La transacción `MM03` muestra los datos de un artículo sin modificarlos. Cualquier usuario con sesión la puede abrir y está en el menú en **Logística ▸ Catálogo**.

## Visualizar un artículo {#visualizar}

1. Ingrese el **SKU** y presione <kbd>F8</kbd>. Si el artículo no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found).
2. La ficha muestra los datos en solo lectura, con el estado y si el artículo tiene seguimiento por lote.
3. <kbd>F3</kbd> vuelve a la pantalla inicial.

## Proveedores del artículo {#proveedores}

Debajo de la ficha, la [lista](/primeros-pasos/listas) **Proveedores del artículo** muestra los proveedores vinculados, con su identificador fiscal, si están activos, el código con que cada uno identifica el artículo y su plazo de entrega. Los vínculos se mantienen en [Gestionar catálogo del proveedor](/compras/catalogo-del-proveedor).

::: tip
En la [Lista del catálogo](./lista-del-catalogo), el doble clic sobre una fila abre esta transacción directamente en la ficha de ese artículo.
:::

<!--@include: ./partes/catalogo-campos.md-->
