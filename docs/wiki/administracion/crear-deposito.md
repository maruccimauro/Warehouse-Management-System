# Crear depósito (ZW11)

La transacción `ZW11` da de alta un depósito. Exige el permiso [`warehouses.manage`](/referencia/permisos#warehouses-manage) y está en el menú en **Logística ▸ Estructura del depósito**. La lista de todos los depósitos está en [Gestionar depósitos](./depositos).

## Crear un depósito {#crear}

1. Complete la ficha: **Depósito**, **Nombre** y, si quiere, **Dirección**.
2. Grabe con <kbd>Ctrl</kbd>+<kbd>S</kbd>. Si el código ya existe, el campo lo marca con [`VALUE_ALREADY_EXISTS`](/referencia/mensajes#value-already-exists). Si no, el sistema informa **Depósito creado.** y deja la ficha vacía para el siguiente.

El depósito nuevo nace activo y con sus zonas y ubicaciones de sistema de recepción, empaque y despacho ya creadas.

<!--@include: ./partes/deposito-campos.md-->
