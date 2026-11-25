# Gestionar zonas (ZW02)

La transacción `ZW02` mantiene las zonas de un depósito. Exige el permiso [`locations.manage`](/referencia/permisos#locations-manage) y está en el menú en **Logística ▸ Estructura del depósito**.

Una **zona** es un área del depósito con una función, por ejemplo almacenamiento o cuarentena. Cada ubicación pertenece a una zona.

## Elegir el depósito {#seleccion}

Ingrese el **Depósito** y ejecute con <kbd>F8</kbd>.

### Depósito {#warehouse}

Depósito cuyas zonas se mantienen. Se elige con la [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#deposito) (<kbd>F4</kbd>), que ofrece los depósitos asignados al usuario, y se completa con el parámetro [`LGN`](/primeros-pasos/parametros-de-usuario#lgn).

## Lista de zonas {#lista}

La [lista](/primeros-pasos/listas) muestra el código, el nombre, el tipo y si la zona es de sistema. Doble clic o <kbd>Enter</kbd> abre la ficha en modo visualizar; **Crear** abre una ficha vacía. <kbd>F3</kbd> vuelve a elegir el depósito.

## Ficha de la zona {#ficha}

En modo visualizar, **Modificar** habilita los cambios. Se graba con <kbd>Ctrl</kbd>+<kbd>S</kbd> y <kbd>F3</kbd> vuelve a la lista, con confirmación si hay datos sin grabar.

Las **zonas de sistema** (recepción, empaque y despacho) las crea el depósito al darse de alta. Se les puede cambiar el nombre, pero no el tipo, y no admiten ubicaciones dadas de alta a mano.

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
