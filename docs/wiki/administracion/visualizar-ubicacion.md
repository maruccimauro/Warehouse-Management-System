# Visualizar ubicación (LS03N)

La transacción `LS03N` muestra los datos de una ubicación sin modificarlos. Cualquier usuario con sesión la puede abrir, para los depósitos que puede consultar, y está en el menú en **Logística ▸ Estructura del depósito**.

## Visualizar una ubicación {#visualizar}

1. Ingrese el **Depósito** y la **Ubicación** y presione <kbd>F8</kbd>. Si la ubicación no existe, la barra de estado lo informa con [`LOCATION_NOT_FOUND`](/referencia/mensajes#location-not-found).
2. La ficha muestra los datos en solo lectura, con el estado, el motivo del bloqueo si lo tiene y, para las ubicaciones de sistema, su función.
3. <kbd>F3</kbd> vuelve a la pantalla inicial.

::: tip
En la [Lista de ubicaciones](./lista-de-ubicaciones), el doble clic sobre una fila abre esta transacción directamente en la ficha de esa ubicación.
:::

<!--@include: ./partes/ubicacion-campos.md-->
