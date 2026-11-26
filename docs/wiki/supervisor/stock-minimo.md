# Gestionar stock mínimo (ZW04)

La transacción `ZW04` mantiene el stock mínimo de los artículos en un depósito. Exige el permiso [`catalog.min_stock.manage`](/referencia/permisos#catalog-min-stock-manage), que tienen el supervisor y el administrador, y está en el menú en **Logística ▸ Inventario**.

El **stock mínimo** es la cantidad de un artículo que se quiere tener siempre en un depósito. Es propio de cada depósito: un mismo artículo puede necesitar 500 unidades en un depósito central y 50 en uno chico. Lo mantiene quien opera el depósito, en los depósitos que tiene asignados.

Cada acción tiene además su transacción propia: [Modificar stock mínimo](./modificar-stock-minimo) (`ZW42`) y [Visualizar stock mínimo](./visualizar-stock-minimo) (`ZW43`). No hay una transacción para crear: todo artículo del catálogo tiene stock mínimo `0` en cada depósito hasta que se le carga otro valor.

## Elegir el depósito {#seleccion}

Ingrese el **Depósito** y, si quiere acotar la lista, el **SKU**. Ejecute con <kbd>F8</kbd>.

### SKU {#sku}

Opcional. Acota los artículos de la lista, como en una [pantalla de selección](/primeros-pasos/pantallas-de-seleccion): un valor, un rango o un patrón con `*`, por ejemplo `TOR*`. Vacío muestra todo el catálogo.

## Lista {#lista}

La [lista](/primeros-pasos/listas) muestra todos los artículos del catálogo, ordenados por SKU, con su nombre, su estado y su **Stock mínimo** en el depósito. La lista filtra además por nombre y por estado del artículo.

- Doble clic o <kbd>Enter</kbd> sobre una fila abre la ficha en modo visualizar.
- <kbd>F3</kbd> vuelve a elegir el depósito.

## Ficha {#ficha}

En modo visualizar, **Modificar** habilita el cambio. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Stock mínimo del artículo … en … grabado.** y la ficha vuelve a visualizar con el valor nuevo. <kbd>F3</kbd> vuelve a la lista, con confirmación si hay datos sin grabar.

El stock mínimo se puede cargar también en un depósito inactivo y para artículos discontinuados o bloqueados: es un dato del artículo, no una operación.

<!--@include: ./partes/stock-minimo-campos.md-->
