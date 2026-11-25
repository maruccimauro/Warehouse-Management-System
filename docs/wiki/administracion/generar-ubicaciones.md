# Generar ubicaciones (LS10)

La transacción `LS10` crea de una vez todas las ubicaciones de una estructura de pasillos, estanterías y niveles. Exige el permiso [`locations.manage`](/referencia/permisos#locations-manage) y está en el menú en **Logística ▸ Estructura del depósito**. Para una ubicación suelta, use [Crear ubicación](./crear-ubicacion).

## Generar un lote {#generar}

1. Complete el **Depósito**, la **Zona** y la plantilla.
2. Debajo de la plantilla, la pantalla muestra cuántas ubicaciones se crean y el primer y el último código, por ejemplo **Se crean 160 ubicaciones, de A-01-01 a D-10-04.**
3. Ejecute con <kbd>F8</kbd> o **Ejecutar**. El sistema pide confirmación con la cantidad, la zona y los códigos.
4. Elija **Generar**. El sistema informa **Se crearon … ubicaciones en la zona …** y deja la plantilla vacía para el lote siguiente.

Las ubicaciones nuevas nacen activas, en la zona elegida, con sus coordenadas completas.

::: warning
El lote es todo o nada: si algún código de la plantilla ya existe en el depósito, no se crea ninguna ubicación y la barra de estado lo informa con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists). Ajuste la plantilla para que no se superponga con lo existente.
:::

## Códigos generados {#codigos}

Cada código une las coordenadas con guiones: el pasillo, la estantería y el nivel con dos dígitos y, si se indica, la posición con dos dígitos. Pasillos A a B, estanterías 1 a 2 y niveles 1 a 2 generan `A-01-01`, `A-01-02`, `A-02-01`, `A-02-02`, `B-01-01` y así hasta `B-02-02`. Con posiciones 1 a 3, el código suma un cuarto tramo: `A-01-01-01`.

## Límite por lote {#limite}

Un lote crea como máximo 2.000 ubicaciones. Si la plantilla supera el límite, la pantalla lo avisa debajo de la plantilla y <kbd>F8</kbd> no envía nada: el mensaje [`LOCATION_BATCH_TOO_LARGE`](/referencia/mensajes#location-batch-too-large) indica la cantidad calculada y el máximo. Divida la estructura en varios lotes, por ejemplo uno por grupo de pasillos.

## Depósito y zona {#destino}

### Depósito {#warehouse}

Depósito donde se crean las ubicaciones, entre los asignados al usuario. Se completa con el parámetro [`LGN`](/primeros-pasos/parametros-de-usuario#lgn) y ofrece la [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#deposito).

### Zona {#zone}

Zona del depósito que recibe las ubicaciones. Se ofrecen solo las zonas propias: las de sistema no admiten ubicaciones dadas de alta a mano.

## Plantilla {#plantilla}

Cada coordenada se indica con un valor desde y un valor hasta, incluidos los dos. El valor hasta tiene que ser igual o mayor que el desde.

### Pasillo desde {#aisles-from}

Primer pasillo: una letra de la A a la Z.

### Pasillo hasta {#aisles-to}

Último pasillo: una letra igual o posterior al pasillo desde.

### Estantería desde {#racks-from}

Primera estantería de cada pasillo: un número de 1 a 99.

### Estantería hasta {#racks-to}

Última estantería de cada pasillo: un número de 1 a 99.

### Nivel desde {#levels-from}

Primer nivel de cada estantería: un número de 1 a 99.

### Nivel hasta {#levels-to}

Último nivel de cada estantería: un número de 1 a 99.

### Posición desde {#positions-from}

Opcional. Primera posición de cada nivel, de 1 a 99. Se completa junto con la posición hasta, o se dejan las dos vacías.

### Posición hasta {#positions-to}

Opcional. Última posición de cada nivel, de 1 a 99.

### Capacidad en unidades {#max-units}

Opcional. Cantidad máxima de unidades que admite cada ubicación del lote: un número entero mayor que cero.
