# Crear producto (MM01)

La transacción `MM01` da de alta un producto en el catálogo. Exige el permiso [`catalog.manage`](/referencia/permisos#catalog-manage) y está en el menú en **Logística ▸ Catálogo**.

Un **producto** es un artículo que el depósito guarda y mueve, identificado por su **SKU**. El catálogo es uno solo para todos los depósitos; el stock mínimo se carga por depósito.

## Crear un producto {#crear}

1. Ingrese el **SKU** del producto nuevo y, si quiere cargar su stock mínimo, el **Depósito**. Presione <kbd>F8</kbd>.
2. Si el SKU ya existe, la barra de estado lo informa con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists) y la ficha no se abre.
3. Complete el **Nombre** y la **Unidad base** y, si corresponde, el resto de los datos.
4. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Producto … creado.** y vuelve a la pantalla inicial.

<kbd>F3</kbd> en la ficha vuelve a la pantalla inicial sin grabar; si hay datos sin grabar, el sistema pide confirmación.

El producto nuevo nace en estado [Activo](/referencia/estados#product-active). El SKU y la unidad base no cambian después del alta.

<!--@include: ./partes/producto-campos.md-->
