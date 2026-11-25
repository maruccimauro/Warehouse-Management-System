# Modificar proveedor (XK02)

La transacción `XK02` cambia los datos de un proveedor y lo activa o desactiva. Exige el permiso [`suppliers.manage`](/referencia/permisos#suppliers-manage) y está en el menú en **Compras ▸ Proveedores**.

## Modificar un proveedor {#modificar}

1. Ingrese el **Proveedor** y presione <kbd>F8</kbd>. Si no existe, la barra de estado lo informa con [`SUPPLIER_NOT_FOUND`](/referencia/mensajes#supplier-not-found).
2. Cambie los datos necesarios.
3. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. El sistema informa **Proveedor … grabado.** y vuelve a la pantalla inicial.

::: info
Un proveedor inactivo no admite órdenes de compra nuevas y deja terminar las abiertas. Sus datos y sus productos se siguen manteniendo.
:::

## Pantalla inicial {#pantalla-inicial}

### Proveedor {#supplier}

Identificador fiscal del proveedor, escrito con o sin separadores. Si no lo conoce, <kbd>F4</kbd> busca por fragmento del nombre o por el identificador fiscal completo.

<!--@include: ./partes/proveedor-campos.md-->
