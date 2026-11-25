# Bloquear o desbloquear ubicación (ZW05)

La transacción `ZW05` bloquea una ubicación con un motivo o la desbloquea. Exige el permiso [`locations.block`](/referencia/permisos#locations-block), que tienen el supervisor y el administrador, y está en el menú en **Logística ▸ Estructura del depósito**.

Una ubicación **bloqueada** no admite operaciones hasta que se desbloquea, por ejemplo mientras se repara una estantería. Ver [estados de la ubicación](/referencia/estados#location).

## Pantalla inicial {#pantalla-inicial}

Ingrese el **Depósito** y la **Ubicación** y continúe con <kbd>F8</kbd>. Si la ubicación no existe, la barra de estado lo informa con [`LOCATION_NOT_FOUND`](/referencia/mensajes#location-not-found).

### Depósito {#warehouse}

Depósito de la ubicación, entre los asignados al usuario. Se completa con el parámetro [`LGN`](/primeros-pasos/parametros-de-usuario#lgn).

### Ubicación {#code}

Código de la ubicación. <kbd>F4</kbd> busca las ubicaciones del depósito por parte del código.

## Bloquear {#bloquear}

Solo se bloquea una ubicación activa.

1. Escriba el **Motivo del bloqueo**.
2. Elija **Bloquear** o grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Ubicación … bloqueada.** y vuelve a la pantalla inicial.

### Motivo del bloqueo {#reason}

Obligatorio, de hasta 255 caracteres. Explica por qué se bloquea; se ve en la ficha de la ubicación y en la [lista de ubicaciones](/administracion/lista-de-ubicaciones).

## Desbloquear {#desbloquear}

Con la ubicación bloqueada, la pantalla muestra el motivo vigente y el botón **Desbloquear**. Después de confirmar, la ubicación vuelve a estar activa y el motivo se borra.

::: info
Una ubicación inactiva o en conteo no se bloquea ni se desbloquea: la pantalla lo indica y no ofrece acciones. En un depósito inactivo, bloquear y desbloquear se rechazan con [`WAREHOUSE_INACTIVE`](/referencia/mensajes#warehouse-inactive).
:::
