## Campos {#campos}

### Depósito {#warehouse}

Depósito del stock mínimo. Se elige con la [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#deposito) (<kbd>F4</kbd>) y se completa con el parámetro de usuario [`LGN`](/primeros-pasos/parametros-de-usuario#lgn). Para gestionar o modificar, el campo ofrece los depósitos donde el usuario opera; para visualizar, todos los que puede consultar. Un depósito que no está entre ellos se informa con [`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found).

### Artículo {#product}

SKU del artículo del [catálogo](/administracion/crear-catalogo). <kbd>F4</kbd> busca por fragmento del SKU, por fragmento del nombre o por el código de barras completo. En la ficha se muestra en solo lectura, con su nombre.

### Stock mínimo {#min-stock}

Cantidad mínima del artículo que se quiere tener en el depósito, en la unidad base del artículo. Es un número entero, `0` o mayor. `0` indica sin stock mínimo, que es el valor de un artículo al que todavía no se le cargó en ese depósito.
