## Pantalla inicial {#pantalla-inicial}

La transacción empieza con dos campos. Complételos y continúe con <kbd>F8</kbd> o **Ejecutar**.

### Depósito {#warehouse}

Depósito de la ubicación. Se elige con la [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#deposito) (<kbd>F4</kbd>) y se completa con el parámetro de usuario [`LGN`](/primeros-pasos/parametros-de-usuario#lgn). Para crear o modificar, el campo ofrece los depósitos asignados al usuario; para visualizar, todos los que el usuario puede consultar.

### Ubicación {#code}

Código de la ubicación dentro del depósito, por ejemplo `A-01-01`. Se escribe en mayúsculas o se convierte al confirmar. Usa letras y números, separados por un guion o un guion bajo. El código no cambia después del alta: para corregirlo, se crea la ubicación nueva y se desactiva la anterior.

Para modificar o visualizar, <kbd>F4</kbd> busca las ubicaciones del depósito: escriba parte del código en la restricción y la lista muestra las que lo contienen.

## Datos de la ubicación {#datos}

### Zona {#zone}

Zona del depósito a la que pertenece la ubicación, con su [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda) (<kbd>F4</kbd>). Se ofrecen solo las zonas propias del depósito: las zonas de sistema (recepción, empaque y despacho) no admiten ubicaciones dadas de alta a mano. Una ubicación de sistema no cambia de zona.

### Estado {#status}

Estado de la ubicación (ver la [referencia de estados](/referencia/estados#location)). Al modificar, una ubicación activa se puede desactivar y una inactiva se puede activar. Una ubicación bloqueada o en conteo no cambia de estado desde esta pantalla: el bloqueo se maneja en [Bloquear o desbloquear ubicación](/supervisor/bloqueo-de-ubicaciones). Una ubicación de sistema no se desactiva.

Debajo de la ficha se ven el estado actual y, si está bloqueada, el motivo.

## Coordenadas {#coordenadas}

Posición física de la ubicación. Son opcionales y admiten hasta 10 caracteres cada una.

### Pasillo {#aisle}

Pasillo del depósito, por ejemplo `A`.

### Estantería {#rack}

Estantería dentro del pasillo, por ejemplo `01`.

### Nivel {#level}

Altura dentro de la estantería, por ejemplo `01`.

### Posición {#position}

Posición dentro del nivel, cuando la estantería se divide en más de un hueco.

## Capacidad {#capacidad}

### Capacidad en unidades {#max-units}

Cantidad máxima de unidades que admite la ubicación. Es opcional: vacío significa sin límite declarado. Si se completa, tiene que ser un número entero mayor que cero.
