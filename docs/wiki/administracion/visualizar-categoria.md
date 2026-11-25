# Visualizar categoría (ZW73)

La transacción `ZW73` muestra los datos de una categoría sin modificarlos. Cualquier usuario con sesión la puede abrir y está en el menú en **Logística ▸ Catálogo**.

## Pantalla inicial {#inicial}

Ingrese la **Categoría** y presione <kbd>F8</kbd>.

### Categoría {#category}

Ruta de la categoría desde el primer nivel, con las categorías separadas por una barra, por ejemplo **Ferretería / Tornillería**. Escrita a mano se acepta sin distinguir mayúsculas; <kbd>F4</kbd> ofrece todas las categorías. Si la ruta no existe, el campo lo marca con [`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found).

## Ficha {#ficha}

La ficha muestra la ruta, el nombre y la categoría superior en solo lectura. <kbd>F3</kbd> vuelve a la pantalla inicial.

<!--@include: ./partes/categoria-campos.md-->
