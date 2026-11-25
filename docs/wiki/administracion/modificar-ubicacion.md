# Modificar ubicación (LS02N)

La transacción `LS02N` cambia la zona, las coordenadas, la capacidad y el estado activo o inactivo de una ubicación. Exige el permiso [`locations.manage`](/referencia/permisos#locations-manage) y está en el menú en **Logística ▸ Estructura del depósito**.

## Modificar una ubicación {#modificar}

1. Ingrese el **Depósito** y la **Ubicación** y presione <kbd>F8</kbd>. Si la ubicación no existe, la barra de estado lo informa con [`LOCATION_NOT_FOUND`](/referencia/mensajes#location-not-found).
2. Cambie los datos necesarios. El código no se modifica.
3. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Ubicación … grabada.** y vuelve a la pantalla inicial.

Para bloquear o desbloquear la ubicación, use [Bloquear o desbloquear ubicación](/supervisor/bloqueo-de-ubicaciones).

<!--@include: ./partes/ubicacion-campos.md-->
