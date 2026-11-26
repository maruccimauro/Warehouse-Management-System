# Gestionar umbrales de ajuste (ZW35)

La transacción `ZW35` mantiene los umbrales de aprobación de ajustes de los artículos en un depósito. Exige el permiso [`inventory.thresholds.manage`](/referencia/permisos#inventory-thresholds-manage), que tienen el administrador y el gerente de operaciones, y está en el menú en **Logística ▸ Inventario**.

Un **umbral de aprobación** es la cantidad a partir de la cual un ajuste de stock de un artículo no se registra en el momento y espera la aprobación de otro usuario. Cada artículo tiene un umbral para los ajustes positivos y otro para los negativos en cada depósito. Es un control central: lo fija la administración o la gerencia de operaciones, y no quien ajusta el stock. Se modifica en los depósitos donde el usuario opera; en los demás solo se visualiza.

Cada acción tiene además su transacción propia: [Modificar umbrales de ajuste](./modificar-umbrales-de-ajuste) (`ZW36`) y [Visualizar umbrales de ajuste](./visualizar-umbrales-de-ajuste) (`ZW37`). No hay una transacción para crear: todo artículo del catálogo está sin umbrales en cada depósito hasta que se le fija uno.

## Elegir el depósito {#seleccion}

Ingrese el **Depósito** y, si quiere acotar la lista, el **SKU**. Ejecute con <kbd>F8</kbd>.

### SKU {#sku}

Opcional. Acota los artículos de la lista, como en una [pantalla de selección](/primeros-pasos/pantallas-de-seleccion): un valor, un rango o un patrón con `*`, por ejemplo `TOR*`. Vacío muestra todo el catálogo.

## Lista {#lista}

La [lista](/primeros-pasos/listas) muestra todos los artículos del catálogo, ordenados por SKU, con su nombre, su estado y sus dos umbrales en el depósito. Un umbral vacío indica que los ajustes de esa dirección no esperan aprobación. La lista filtra además por nombre y por estado del artículo.

- Doble clic o <kbd>Enter</kbd> sobre una fila abre la ficha en modo visualizar.
- <kbd>F3</kbd> vuelve a elegir el depósito.

## Ficha {#ficha}

En modo visualizar, **Modificar** habilita los cambios. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Umbrales de ajuste del artículo … en … grabados.** y la ficha vuelve a visualizar con los valores nuevos. <kbd>F3</kbd> vuelve a la lista, con confirmación si hay datos sin grabar.

Los umbrales se pueden fijar también en un depósito inactivo y para artículos discontinuados o bloqueados: son un dato del artículo, no una operación.

<!--@include: ./partes/umbrales-de-ajuste-campos.md-->
