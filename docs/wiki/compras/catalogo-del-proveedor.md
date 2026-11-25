# Gestionar catálogo del proveedor (ZW06)

La transacción `ZW06` mantiene los artículos que provee cada proveedor. Exige el permiso [`suppliers.manage`](/referencia/permisos#suppliers-manage) y está en el menú en **Compras ▸ Proveedores**.

El **vínculo** entre un artículo y un proveedor indica que ese proveedor lo vende. Una orden de compra a un proveedor solo admite artículos vinculados a él. El vínculo guarda además el código con que el proveedor identifica el artículo y su plazo de entrega.

Cada acción tiene además su transacción propia: [Crear artículo del proveedor](./crear-articulo-del-proveedor) (`ZW61`), [Modificar artículo del proveedor](./modificar-articulo-del-proveedor) (`ZW62`) y [Visualizar artículo del proveedor](./visualizar-articulo-del-proveedor) (`ZW63`).

## Elegir el proveedor {#seleccion}

Ingrese el **Proveedor** y ejecute con <kbd>F8</kbd>. Si no existe, la barra de estado lo informa con [`SUPPLIER_NOT_FOUND`](/referencia/mensajes#supplier-not-found).

### Proveedor {#supplier}

[Número](./visualizar-proveedor#number) del proveedor, con o sin los ceros a la izquierda: `12` y `000012` son el mismo. Si no lo conoce, <kbd>F4</kbd> busca por número, por fragmento del nombre o por el identificador fiscal completo.

## Artículos vinculados {#lista}

La [lista](/primeros-pasos/listas) muestra los artículos del proveedor, ordenados por artículo, con su nombre, su estado, el código del proveedor y el plazo de entrega.

- **Vincular artículo** abre una ficha vacía para agregar uno.
- Doble clic o <kbd>Enter</kbd> sobre una fila abre el vínculo en modo visualizar.
- <kbd>F3</kbd> vuelve a elegir el proveedor.

## Ficha del vínculo {#ficha}

En modo visualizar, **Modificar** habilita los cambios y **Quitar vínculo** lo elimina, previa confirmación. Se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd>, y cada grabación queda hecha en el momento. <kbd>F3</kbd> vuelve a la lista, con confirmación si hay datos sin grabar.

::: warning
Un artículo sin vínculo no se puede incluir en órdenes de compra nuevas a ese proveedor. Las órdenes ya aprobadas no se ven afectadas.
:::

Se pueden vincular artículos discontinuados o bloqueados y proveedores inactivos: el vínculo es un dato maestro y su uso lo controlan las órdenes de compra.

<!--@include: ./partes/articulo-del-proveedor-campos.md-->
