# Modificar categoría (ZW72)

La transacción `ZW72` cambia el nombre de una categoría o la categoría de la que depende. Exige el permiso [`catalog.manage`](/referencia/permisos#catalog-manage) y está en el menú en **Logística ▸ Catálogo**.

## Pantalla inicial {#inicial}

Ingrese la **Categoría** y presione <kbd>F8</kbd>.

### Categoría {#category}

Ruta de la categoría desde el primer nivel, con las categorías separadas por una barra, por ejemplo **Ferretería / Tornillería**. Escrita a mano se acepta sin distinguir mayúsculas; <kbd>F4</kbd> ofrece todas las categorías. Si la ruta no existe, el campo lo marca con [`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found).

## Ficha {#ficha}

Cambie el **Nombre** o la **Categoría superior** y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Categoría grabada.** y vuelve a la pantalla inicial con la ruta nueva. <kbd>F3</kbd> vuelve sin grabar, con confirmación si hay datos sin grabar.

::: warning
Cambiar la categoría superior mueve también las subcategorías y los artículos de la categoría, y cambia su ruta.
:::

<!--@include: ./partes/categoria-campos.md-->
