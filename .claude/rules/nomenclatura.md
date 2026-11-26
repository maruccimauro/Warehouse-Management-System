# Regla: nombres consistentes en la interfaz y la wiki

Un mismo objeto y una misma acción se nombran igual en toda la aplicación: títulos de transacción, carpetas del menú, botones, mensajes y páginas de la wiki. Antes de agregar o renombrar una transacción, una carpeta o un botón, se revisa cómo se nombró lo existente y se sigue el patrón; si el patrón no alcanza, se plantea el cambio y se actualiza todo lo que lo usa en el mismo lote.

## Transacciones

- Título con la forma **Verbo + objeto**, en infinitivo y en singular cuando opera sobre un registro: **Crear producto**, **Modificar ubicación**, **Visualizar proveedor**, **Bloquear o desbloquear ubicación**.
- Verbos fijos por actividad, sin sinónimos:
  - **Crear**: alta. Para varios registros a la vez, **Crear múltiples <objetos>** (no "Generar").
  - **Modificar**: cambio de un registro existente.
  - **Visualizar**: consulta de un registro sin editar.
  - **Gestionar <objetos>**: transacción de gestión que reúne crear, modificar y visualizar. Para tablas de configuración y vínculos (depósitos, zonas, categorías, unidades, catálogo del proveedor) lleva lista y ficha; para un maestro con trío (usuarios) lleva pantalla inicial con el registro y **Crear**, **Modificar** y **Visualizar** en la barra de aplicación. No reemplaza a las individuales: cada acción tiene además su transacción **Crear**, **Modificar** y **Visualizar** (ADR-016). Un dato que existe para todo registro con su valor por defecto (stock mínimo, umbrales de ajuste) no tiene **Crear**: su gestión lista todos los registros con su valor.
  - **Bloquear**, **Desbloquear**, **Activar**, **Desactivar**: cambio de estado. Las bajas son lógicas: no se usa "Eliminar" para datos maestros con historia.
  - **Transferir**, **Ajustar**, **Cargar** (stock inicial) y **Revertir**: operaciones de inventario. Una reversión es siempre **Revertir**, nunca "Anular" ni "Cancelar" (**Cancelar** es el botón de los diálogos). Única excepción: el texto de los códigos de movimiento que genera una reversión empieza con **Anulación de …** (102 "Anulación de entrada por orden de compra"), porque nombra el movimiento y no la acción (decisión del usuario del 25/11/2026). La acción, la transacción, los botones y los mensajes siguen diciendo **Revertir**, y la referencia de movimientos de la wiki explica que una anulación es el movimiento que genera una reversión. Una reversión por menos que el total es una **reversión parcial** ("Revertir 5 de 10"); el texto del código no cambia con la cantidad. Los pares 701/702 y 711/712 se revierten entre sí sin "Anulación" en el texto: la wiki lo aclara en la misma referencia (decisión del usuario del 25/11/2026).
  - **Aprobar** y **Rechazar**: decisión sobre una solicitud pendiente.
- El objeto de inventario es **stock** (**Transferir stock**, **Lista de stock**) y cada registro del libro es una **operación** con sus **movimientos**.
- Única excepción: los listados se titulan **Lista de <objetos>** (**Lista de productos**).
- El objeto se nombra igual que su carpeta del menú y su sección de la wiki.

## Vocabulario fijo

- **Catálogo**: el maestro único de lo que el depósito guarda y mueve, compartido por todos los depósitos. Las transacciones se titulan **Crear catálogo**, **Modificar catálogo**, **Visualizar catálogo**, **Lista del catálogo** y **Gestionar catálogo del proveedor**.
- **Artículo**: cada registro del catálogo, identificado por su SKU ("El artículo TOR-M8 no existe"). No se usa "producto" en la interfaz ni en la wiki: el catálogo puede incluir elementos que no son productos. Los códigos técnicos (`PRODUCT_NOT_FOUND`, `catalog.manage`, tablas y rutas de la API) no cambian.
- **Proveedor**: se identifica por su **número** interno (`000012`); el identificador fiscal es un dato más del proveedor.

## Carpetas del menú

- Nombre del objeto (**Catálogo**, **Proveedores**, **Usuarios**, **Roles**) o del área funcional (**Logística**, **Compras**, **Sistema**). Una carpeta no lleva el nombre de un concepto distinto del objeto que contiene: debajo de **Catálogo** van **Crear catálogo**, **Modificar catálogo**, y así.

## Botones de diálogos y pantallas

- **Aplicar**: toma lo editado en un diálogo y lo cierra (selección múltiple, disposición, filtro). No se usa "Copiar" ni "Aceptar" para esta acción.
- **Cancelar**: cierra sin tomar cambios.
- **Borrar pestaña** y **Borrar todo**: vacían lo visible o todo el diálogo.
- **Grabar** (Ctrl+S) y **Ejecutar** (F8) en la barra estándar, sin variantes.

## Wiki

- La página de una transacción lleva su título exacto seguido del código: `# Crear múltiples ubicaciones (LS10)`. El nombre de archivo sigue el título, en minúsculas y con guiones.
- Al renombrar, se actualizan el título, el `sidebar`, `referencia/transacciones.md`, los enlaces y la clave de ayuda en `helpLinks.ts`; las anclas `{#...}` no cambian.
