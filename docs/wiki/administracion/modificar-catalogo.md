# Modificar catálogo (MM02)

La transacción `MM02` cambia los datos generales de un artículo y su estado. Exige el permiso [`catalog.manage`](/referencia/permisos#catalog-manage) y está en el menú en **Logística ▸ Catálogo**.

## Modificar un artículo {#modificar}

1. Ingrese el **SKU** y presione <kbd>F8</kbd>. Si el artículo no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found).
2. Cambie los datos necesarios. El SKU y la unidad base no se modifican.
3. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Artículo … grabado.** y vuelve a la pantalla inicial.

::: warning
Discontinuar o bloquear un artículo limita lo que se puede hacer con él a partir de ese momento. Antes de cambiar el estado, revise su efecto en la [referencia de estados](/referencia/estados#product).
:::

Los proveedores del artículo se mantienen en [Gestionar catálogo del proveedor](/compras/catalogo-del-proveedor); su stock mínimo en cada depósito, en [Gestionar stock mínimo](/supervisor/stock-minimo), y sus umbrales de aprobación de ajustes, en [Gestionar umbrales de ajuste](/administracion/umbrales-de-ajuste).

<!--@include: ./partes/catalogo-campos.md-->
