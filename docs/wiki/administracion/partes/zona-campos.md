## Datos de la zona {#datos}

### Zona {#code}

Código de la zona, de hasta 20 caracteres: letras y números, separados por un guion o un guion bajo. Se guarda en mayúsculas, es único dentro del depósito ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)) y no cambia después del alta.

### Nombre {#name}

Nombre de la zona, obligatorio, de hasta 120 caracteres.

### Tipo {#type}

Función del área. Es un dato descriptivo de la zona:

| Tipo | Área destinada a |
| - | - |
| **Recepción** | Mercadería recibida que todavía no se ubicó |
| **Almacenamiento** | Stock guardado |
| **Preparación** | Stock para preparar pedidos |
| **Empaque** | Pedidos en proceso de empaque |
| **Despacho** | Pedidos listos para salir |
| **Cuarentena** | Mercadería retenida a la espera de una decisión |
