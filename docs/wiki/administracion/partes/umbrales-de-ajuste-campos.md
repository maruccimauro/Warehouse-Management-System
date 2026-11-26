## Campos {#campos}

### Depósito {#warehouse}

Depósito de los umbrales. Se elige con la [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#deposito) (<kbd>F4</kbd>) y se completa con el parámetro de usuario [`LGN`](/primeros-pasos/parametros-de-usuario#lgn). Ofrece todos los depósitos que el usuario puede consultar: fijar un umbral no exige operar en el depósito. Un depósito que no está entre ellos se informa con [`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found).

### Artículo {#product}

SKU del artículo del [catálogo](./crear-catalogo). <kbd>F4</kbd> busca por fragmento del SKU, por fragmento del nombre o por el código de barras completo. En la ficha se muestra en solo lectura, con su nombre.

### Umbral de aprobación de ajuste positivo {#adjustment-in-threshold}

Opcional. Cantidad máxima del artículo, en la unidad base, que una posición de un ajuste de stock positivo (un sobrante encontrado o una corrección en más) suma en el depósito sin necesitar aprobación. Una posición que la supera no mueve stock: queda pendiente de aprobación. Una posición igual al umbral no lo supera. Vacío indica sin umbral: todos los ajustes positivos del artículo se registran en el momento. Si se completa, es un número entero mayor que cero.

### Umbral de aprobación de ajuste negativo {#adjustment-out-threshold}

Opcional. Igual que el anterior, para los ajustes negativos (daño, pérdida o corrección en menos). Los dos umbrales son independientes: un artículo puede tener umbral para las salidas y no para las entradas.

::: info
Cada artículo tiene sus propios umbrales en cada depósito, porque no pesa lo mismo la diferencia de un motor que la de un tornillo. Los cambios de umbral quedan registrados en la auditoría con el valor anterior y el nuevo.
:::
