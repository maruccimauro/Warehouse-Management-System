# Listas

Los resultados se muestran en **listas** que permiten ordenar, filtrar, totalizar, elegir columnas y exportar. La barra arriba de la lista reúne las funciones, que actúan sobre la columna de la celda con foco.

## Ordenar y filtrar {#ordenar-y-filtrar}

- **Orden ascendente** y **Orden descendente** ordenan por la columna con foco.
- **Definir filtro** abre un diálogo para la columna, con el operador que corresponde a sus datos: **Contiene**, **Igual a** o una lista de **Valores**. Los filtros activos se ven arriba de la lista y se quitan de a uno; **Quitar todos los filtros** los quita juntos.

En las listas de datos maestros, que pueden tener miles de filas, el servidor ordena, filtra y pagina; solo se ofrece ordenar y filtrar por las columnas que lo admiten.

## Totales y subtotales {#totales}

**Total** suma una columna numérica y **Subtotales** agrupa por la columna con foco. Están disponibles en las listas que se cargan completas, como los informes con resultado acotado; en una lista paginada un total sumaría solo la página visible.

## Columnas {#columnas}

**Modificar disposición** elige las columnas visibles y su orden. **Disposición inicial** vuelve a la de la pantalla. La disposición dura mientras la transacción está abierta.

**Ocultar columna**, en el menú contextual de la columna, la quita de la vista.

## Exportar {#exportar}

**Exportar a hoja de cálculo** descarga un archivo CSV con todo el resultado filtrado, con las columnas visibles y en el orden en que se ven. El archivo usa punto y coma como separador y coma decimal, y abre directamente en una planilla de cálculo.

En las listas paginadas se exportan hasta 5.000 filas; si el resultado tiene más, lo avisa el mensaje W [`GRID_EXPORT_TRUNCATED`](/referencia/mensajes#grid-export-truncated). Para exportar el resto, restrinja la selección.

## Teclado {#teclado}

La lista recibe el foco con una sola parada de <kbd>Tab</kbd>. Dentro de ella:

| Tecla | Acción |
| - | - |
| <kbd>←</kbd> <kbd>→</kbd> <kbd>↑</kbd> <kbd>↓</kbd> | Mover entre celdas |
| <kbd>Inicio</kbd> y <kbd>Fin</kbd> | Primera y última celda de la fila |
| <kbd>Ctrl</kbd>+<kbd>Inicio</kbd> y <kbd>Ctrl</kbd>+<kbd>Fin</kbd> | Primera y última celda de la lista |
| <kbd>RePág</kbd> y <kbd>AvPág</kbd> | Subir y bajar una página de filas |
| <kbd>Enter</kbd> o doble clic | Abrir la fila o tomar el valor en una ayuda de búsqueda |
| <kbd>Espacio</kbd> | Seleccionar la fila, en las listas con selección múltiple |
| <kbd>Mayús</kbd>+<kbd>F10</kbd> o clic secundario | Menú contextual de la columna |
