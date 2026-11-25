# Crear proveedor (XK01)

La transacción `XK01` da de alta un proveedor. Exige el permiso [`suppliers.manage`](/referencia/permisos#suppliers-manage) y está en el menú en **Compras ▸ Proveedores**.

Un **proveedor** es la empresa a la que se le compra mercadería. Los proveedores son los mismos para todos los depósitos.

## Crear un proveedor {#crear}

1. La transacción abre directamente la ficha vacía.
2. Complete el **Identificador fiscal** y el **Nombre** y, si corresponde, el contacto.
3. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. Si el identificador fiscal ya pertenece a otro proveedor, el campo lo marca con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists). Si no, el sistema informa **Proveedor … creado.** y deja la ficha vacía para el siguiente.

<kbd>F3</kbd> sale de la transacción; si hay datos sin grabar, el sistema pide confirmación.

El proveedor nuevo nace en estado [Activo](/referencia/estados#supplier-active). Para vincularle los productos que provee, use [Productos del proveedor](./productos-del-proveedor).

<!--@include: ./partes/proveedor-campos.md-->
