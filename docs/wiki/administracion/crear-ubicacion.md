# Crear ubicación (LS01N)

La transacción `LS01N` da de alta una ubicación en un depósito, una por vez. Exige el permiso [`locations.manage`](/referencia/permisos#locations-manage) y está en el menú en **Logística ▸ Estructura del depósito**. Para crear muchas ubicaciones con una misma estructura, use [Generar ubicaciones](./generar-ubicaciones).

Una **ubicación** es el lugar físico donde se guarda la mercadería, identificado por un código único dentro del depósito.

## Crear una ubicación {#crear}

1. Ingrese el **Depósito** y el código de la nueva **Ubicación** y presione <kbd>F8</kbd>.
2. Si el código ya existe en el depósito, la barra de estado lo informa con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists) y la ficha no se abre.
3. Complete la **Zona** y, si corresponde, las coordenadas y la capacidad.
4. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Ubicación … creada.** y vuelve a la pantalla inicial, lista para la siguiente.

<kbd>F3</kbd> en la ficha vuelve a la pantalla inicial sin grabar; si hay datos sin grabar, el sistema pide confirmación.

La ubicación nueva nace en estado [Activa](/referencia/estados#location-active).

<!--@include: ./partes/ubicacion-campos.md-->
