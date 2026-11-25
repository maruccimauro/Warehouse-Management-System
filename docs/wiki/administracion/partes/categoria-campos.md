## Datos de la categoría {#datos}

### Nombre {#name}

Nombre de la categoría, obligatorio, de hasta 120 caracteres. No se repite entre las categorías que dependen de la misma categoría superior, ni entre las del primer nivel ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)). Dos categorías de ramas distintas sí pueden llamarse igual.

### Categoría superior {#parent}

Opcional. Categoría de la que depende, escrita con su ruta o elegida con <kbd>F4</kbd> ([`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found) si no existe). Vacío: la categoría queda en el primer nivel.

Cambiarla mueve la categoría con todas sus subcategorías y sus artículos. El sistema rechaza el cambio si la categoría superior es la propia categoría o una de sus subcategorías ([`CATEGORY_HIERARCHY_CYCLE`](/referencia/mensajes#category-hierarchy-cycle)) o si la jerarquía pasaría de 5 niveles ([`CATEGORY_HIERARCHY_TOO_DEEP`](/referencia/mensajes#category-hierarchy-too-deep)).
