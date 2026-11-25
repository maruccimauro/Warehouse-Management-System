# Gestionar unidades de medida (ZW08)

La transacción `ZW08` da de alta las unidades de medida y modifica su nombre. Exige el permiso [`catalog.manage`](/referencia/permisos#catalog-manage) y está en el menú en **Logística ▸ Catálogo**.

Una **unidad de medida** es la unidad en que se cuentan las cantidades de un artículo, por ejemplo `UN` (unidad) o `KG` (kilogramo). Cada artículo tiene una **unidad base**, que se elige al crearlo. Las unidades no se eliminan.

Cada acción tiene además su transacción propia: [Crear unidad de medida](./crear-unidad-de-medida) (`ZW81`), [Modificar unidad de medida](./modificar-unidad-de-medida) (`ZW82`) y [Visualizar unidad de medida](./visualizar-unidad-de-medida) (`ZW83`).

## Lista de unidades de medida {#lista}

Al abrir la transacción se ve la [lista](/primeros-pasos/listas) de las unidades con su código y su nombre. Se ordena y se filtra por las dos columnas.

- Doble clic o <kbd>Enter</kbd> sobre una fila abre la ficha de la unidad en modo visualizar.
- **Crear** abre una ficha vacía.

## Ficha de la unidad de medida {#ficha}

En modo visualizar los datos son de solo lectura y **Modificar** habilita el cambio del nombre.

En modo crear o modificar se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd>; la ficha vuelve a modo visualizar con lo grabado. <kbd>F3</kbd> vuelve a la lista; si hay datos sin grabar, el sistema pide confirmación.

<!--@include: ./partes/unidad-de-medida-campos.md-->
