## Pantalla inicial {#pantalla-inicial}

La transacción empieza con el SKU. Complételo y continúe con <kbd>F8</kbd> o **Ejecutar**.

### SKU {#sku}

Código del artículo, de hasta 40 caracteres: letras y números, separados por un guion o un guion bajo, por ejemplo `TOR-M6-30`. Se escribe en mayúsculas o se convierte al confirmar, es único en el catálogo y no cambia después del alta.

Para modificar o visualizar, <kbd>F4</kbd> busca en el catálogo por fragmento del SKU, por fragmento del nombre o por el código de barras completo.

## Datos generales {#datos-generales}

Los datos generales valen para todos los depósitos. Lo propio del artículo en cada depósito se mantiene aparte, con otro responsable: el stock mínimo en [Gestionar stock mínimo](/supervisor/stock-minimo) y los umbrales de aprobación de ajustes en [Gestionar umbrales de ajuste](/administracion/umbrales-de-ajuste).

### Nombre {#name}

Obligatorio, de hasta 200 caracteres.

### Descripción {#description}

Opcional, de hasta 2.000 caracteres.

### Estado {#status}

Define qué operaciones admite el artículo: **Activo**, **Discontinuado** o **Bloqueado** (ver la [referencia de estados](/referencia/estados#product)). El artículo nace activo; el estado se cambia al modificar. Debajo de la ficha se ve el estado actual, con un enlace a su explicación.

### Código de barras {#barcode}

Opcional, de hasta 64 caracteres, sin espacios. Es único en el catálogo ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)) y distingue mayúsculas y minúsculas. Si es solo numérico y tiene 8, 12, 13 o 14 dígitos, el sistema controla además el dígito verificador: un error de tipeo se informa al grabar.

### Categoría {#category}

Opcional. Agrupa artículos afines. Se muestra con su ruta desde la categoría superior, por ejemplo **Ferretería / Tornillería**, y se elige con <kbd>F4</kbd>. Escrita a mano, se acepta sin distinguir mayúsculas. Las categorías se mantienen en [Gestionar categorías](/administracion/categorias).

### Unidad base {#base-uom}

Obligatoria. Unidad en que se cuenta el stock del artículo, por ejemplo `UN` (unidad) o `G` (gramo), con su <kbd>F4</kbd>. Se elige al crear y no cambia después: todas las cantidades del artículo quedan expresadas en ella. Las unidades se mantienen en [Gestionar unidades de medida](/administracion/unidades-de-medida).

## Peso y medidas {#peso-y-medidas}

Opcionales. Si se completan, son números enteros mayores que cero.

### Peso (g) {#weight-grams}

Peso de una unidad base, en gramos.

### Largo (mm) {#length-mm}

Largo de una unidad base, en milímetros.

### Ancho (mm) {#width-mm}

Ancho de una unidad base, en milímetros.

### Alto (mm) {#height-mm}

Alto de una unidad base, en milímetros.
