## Datos del proveedor {#datos}

### Identificador fiscal {#tax-id}

Obligatorio. Número de identificación tributaria del proveedor, por ejemplo el CUIT `30-71234567-1`. Se puede escribir con espacios, puntos, guiones o barras: el sistema los quita y lo guarda en mayúsculas, con 5 a 20 letras o números. Es único: dos proveedores no comparten identificador fiscal ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)). A diferencia de otros códigos, se puede corregir al modificar.

### Nombre {#name}

Razón social o nombre del proveedor, obligatorio, de hasta 200 caracteres.

### Estado {#status}

**Activo** o **Inactivo** (ver la [referencia de estados](/referencia/estados#supplier)). El proveedor nace activo; el estado se cambia al modificar. Un proveedor no se borra: cuando deja de operar, se desactiva.

## Contacto {#contacto}

### Email {#email}

Opcional, de hasta 255 caracteres. Se guarda en minúsculas.

### Teléfono {#phone}

Opcional, de hasta 40 caracteres, con el formato que el proveedor use.
