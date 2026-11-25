## Datos del vínculo {#datos}

### Artículo {#product}

SKU del artículo, con <kbd>F4</kbd> para buscarlo en el catálogo. Se elige al vincular y no cambia después: para reemplazarlo, se quita el vínculo y se crea otro. Si el artículo no existe, la barra de estado lo informa con [`PRODUCT_NOT_FOUND`](/referencia/mensajes#product-not-found); si ya está vinculado al proveedor, con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists).

### Código del proveedor {#supplier-sku}

Opcional, de hasta 60 caracteres. Código con que el proveedor identifica el artículo en sus listas y facturas. Se guarda tal como se escribe, con mayúsculas y minúsculas, y no se repite entre artículos del mismo proveedor ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)); otro proveedor puede usar el mismo código.

### Plazo de entrega (días) {#lead-time-days}

Opcional. Días corridos desde el pedido hasta la entrega, de 0 a 365.
