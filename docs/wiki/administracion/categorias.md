# Gestionar categorías (ZW07)

La transacción `ZW07` da de alta las categorías del catálogo y modifica su nombre y su lugar en la jerarquía. Exige el permiso [`catalog.manage`](/referencia/permisos#catalog-manage) y está en el menú en **Logística ▸ Catálogo**.

Una **categoría** agrupa artículos afines. Las categorías forman una jerarquía de hasta 5 niveles: cada una puede depender de una **categoría superior**, y se identifica por su **ruta** desde el primer nivel, por ejemplo **Ferretería / Tornillería**. Las categorías no se eliminan.

Cada acción tiene además su transacción propia: [Crear categoría](./crear-categoria) (`ZW71`), [Modificar categoría](./modificar-categoria) (`ZW72`) y [Visualizar categoría](./visualizar-categoria) (`ZW73`).

## Lista de categorías {#lista}

Al abrir la transacción se ve la [lista](/primeros-pasos/listas) de todas las categorías, ordenada por ruta, con la ruta, el nombre y la categoría superior. Se ordena y se filtra por cualquiera de las tres columnas.

- Doble clic o <kbd>Enter</kbd> sobre una fila abre la ficha de la categoría en modo visualizar.
- **Crear** abre una ficha vacía.

## Ficha de la categoría {#ficha}

La ficha muestra la ruta de la categoría. En modo visualizar los datos son de solo lectura y **Modificar** habilita los cambios.

En modo crear o modificar se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd>; la ficha vuelve a modo visualizar con lo grabado. <kbd>F3</kbd> vuelve a la lista; si hay datos sin grabar, el sistema pide confirmación.

<!--@include: ./partes/categoria-campos.md-->
