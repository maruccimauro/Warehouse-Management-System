# Visualizar proveedor (XK03)

La transacción `XK03` muestra los datos de un proveedor y sus artículos sin modificarlos. Cualquier usuario con sesión la puede abrir y está en el menú en **Compras ▸ Proveedores**.

## Visualizar un proveedor {#visualizar}

1. Ingrese el **Proveedor** y presione <kbd>F8</kbd>. Si no existe, la barra de estado lo informa con [`SUPPLIER_NOT_FOUND`](/referencia/mensajes#supplier-not-found).
2. La ficha muestra los datos en solo lectura y el estado.
3. <kbd>F3</kbd> vuelve a la pantalla inicial.

## Catálogo del proveedor {#productos}

Debajo de la ficha, la [lista](/primeros-pasos/listas) **Catálogo del proveedor** muestra los artículos vinculados, ordenados por artículo, con su estado, el código con que el proveedor los identifica y el plazo de entrega. Los vínculos se mantienen en [Gestionar catálogo del proveedor](./catalogo-del-proveedor).

## Pantalla inicial {#pantalla-inicial}

### Proveedor {#supplier}

[Número](#number) del proveedor, con o sin los ceros a la izquierda: `12` y `0000000012` son el mismo. Si no lo conoce, <kbd>F4</kbd> busca por número, por fragmento del nombre o por el identificador fiscal completo.

<!--@include: ./partes/proveedor-campos.md-->
