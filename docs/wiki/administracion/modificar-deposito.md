# Modificar depósito (ZW12)

La transacción `ZW12` cambia los datos de un depósito y lo activa o desactiva. Exige el permiso [`warehouses.manage`](/referencia/permisos#warehouses-manage) y está en el menú en **Logística ▸ Estructura del depósito**.

## Pantalla inicial {#inicial}

Ingrese el **Depósito** y presione <kbd>F8</kbd>.

### Depósito {#warehouse}

Código del depósito. <kbd>F4</kbd> ofrece los depósitos que el usuario puede consultar, activos e inactivos. Se completa con el parámetro de usuario [`LGN`](/primeros-pasos/parametros-de-usuario#lgn).

## Ficha {#ficha}

Cambie el **Nombre** o la **Dirección** y grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>: el sistema informa **Depósito grabado.** y vuelve a la pantalla inicial. <kbd>F3</kbd> vuelve sin grabar, con confirmación si hay datos sin grabar.

**Desactivar** y **Activar** cambian el estado del depósito, después de confirmar.

::: warning
Un depósito inactivo no admite operaciones, como bloquear o desbloquear ubicaciones ([`WAREHOUSE_INACTIVE`](/referencia/mensajes#warehouse-inactive)). Sigue visible para consultas y admite el mantenimiento de sus zonas y ubicaciones. Ver [estados del depósito](/referencia/estados#warehouse).
:::

<!--@include: ./partes/deposito-campos.md-->
