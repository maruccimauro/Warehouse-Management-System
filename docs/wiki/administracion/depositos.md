# Gestionar depósitos (ZW01)

La transacción `ZW01` da de alta los depósitos, modifica sus datos y los activa o desactiva. Exige el permiso [`warehouses.manage`](/referencia/permisos#warehouses-manage) y está en el menú en **Logística ▸ Estructura del depósito**.

Un **depósito** es un establecimiento físico con su propio stock, sus zonas y sus ubicaciones.

## Lista de depósitos {#lista}

Al abrir la transacción se ve la [lista](/primeros-pasos/listas) de los depósitos, activos e inactivos, con su código, nombre, dirección y estado. Se ordena por código o nombre y se filtra por código, nombre o estado.

- Doble clic o <kbd>Enter</kbd> sobre una fila abre la ficha del depósito en modo visualizar.
- **Crear** abre una ficha vacía.

## Ficha del depósito {#ficha}

En modo visualizar los datos son de solo lectura y la barra de la pantalla ofrece:

| Botón | Qué hace |
| - | - |
| **Modificar** | Pasa la ficha a modo modificar |
| **Desactivar** | Desactiva el depósito, después de confirmar |
| **Activar** | Vuelve a activar un depósito inactivo, después de confirmar |

En modo crear o modificar se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd>; la ficha vuelve a modo visualizar con lo grabado. <kbd>F3</kbd> vuelve a la lista; si hay datos sin grabar, el sistema pide confirmación.

Un depósito nuevo nace activo y con sus zonas y ubicaciones de sistema de recepción, empaque y despacho ya creadas.

::: warning
Un depósito inactivo no admite operaciones, como bloquear o desbloquear ubicaciones ([`WAREHOUSE_INACTIVE`](/referencia/mensajes#warehouse-inactive)). Sigue visible para consultas y admite el mantenimiento de sus zonas y ubicaciones. Ver [estados del depósito](/referencia/estados#warehouse).
:::

### Depósito {#code}

Código del depósito, de hasta 20 caracteres: letras y números, separados por un guion o un guion bajo. Se guarda en mayúsculas, tiene que ser único ([`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists)) y no cambia después del alta.

### Nombre {#name}

Nombre del depósito, obligatorio, de hasta 120 caracteres.

### Dirección {#address}

Dirección del depósito, opcional, de hasta 500 caracteres.
