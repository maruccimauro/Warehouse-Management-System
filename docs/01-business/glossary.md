# Glosario

Términos del dominio, su nombre en el código y su definición. El código usa siempre el término en inglés de la segunda columna; la documentación y la interfaz usan el término en español.

## Infraestructura física

| Término | Código | Definición |
| - | - | - |
| Depósito | `Warehouse` | Instalación física donde se almacena mercadería. Unidad de alcance de los permisos de usuario |
| Zona | `Zone` | Agrupación de ubicaciones de un depósito con una función operativa: recepción, almacenamiento, picking, packing, despacho o cuarentena |
| Ubicación | `Location` | Lugar físico identificable donde puede haber stock, con código único dentro del depósito (por ejemplo `A01-01-01`: pasillo, estantería, nivel) |
| Ubicación de sistema | `Location.system_role` | Ubicación creada automáticamente con el depósito para los flujos internos: muelle de recepción (`receiving`), packing (`packing`) y despacho (`dispatch`). `is_system` es una columna generada a partir de `system_role` |
| Muelle de recepción | `receiving` | Ubicación de sistema donde ingresa la mercadería recibida antes de ubicarse en almacenamiento |
| Ubicación bloqueada | `Location.status = blocked` | Ubicación que no admite movimientos, por mantenimiento, daño o decisión de un supervisor |
| Ubicación en conteo | `Location.status = counting` | Ubicación incluida en un inventario físico activo; no admite movimientos hasta que el conteo se aprueba o cancela |

## Catálogo

| Término | Código | Definición |
| - | - | - |
| Producto | `Product` | Artículo que se almacena. Entidad maestra, independiente de sus existencias |
| SKU | `sku` | Código interno único del producto |
| Código de barras | `barcode` | Código EAN, UPC u otro impreso en el producto; único si existe |
| Unidad base | `base_uom` | Unidad mínima en la que se expresan todas las cantidades del producto (unidad, gramo, mililitro) |
| Categoría | `Category` | Clasificación jerárquica de productos |
| Stock mínimo | `min_stock` | Cantidad disponible por debajo de la cual se genera una alerta de stock crítico |
| Proveedor | `Supplier` | Empresa que provee productos mediante órdenes de compra |
| Cliente | `Customer` | Destinatario de los pedidos |

## Inventario

| Término | Código | Definición |
| - | - | - |
| Existencia | `Stock` | Cantidad de un producto en una ubicación (y lote, en versiones futuras). Proyección del estado actual |
| Stock físico | `quantity_on_hand` | Cantidad presente en la ubicación |
| Stock reservado | `quantity_reserved` | Parte del stock físico comprometida para un pedido |
| Stock bloqueado | `quantity_blocked` | Parte del stock físico no utilizable (cuarentena, daño en análisis) |
| Stock disponible | calculado | Físico menos reservado menos bloqueado. Es lo único que puede comprometerse o retirarse libremente |
| Operación de inventario | `InventoryOperation` | Hecho de negocio confirmado que modifica stock: recepción, transferencia, ajuste, picking, despacho, reversión. Agrupa uno o más movimientos |
| Movimiento | `StockMovement` | Línea de una operación que modifica una fila de existencia con un delta firmado |
| Código de movimiento | `movement_code` | Número que clasifica un movimiento, tomado de las clases de movimiento de SAP donde hay equivalente (101 entrada por orden de compra, 601 salida por despacho) y del rango 9xx para los movimientos internos del depósito. Cada código tiene su código de reversión (101 y 102) |
| Reversión | `reversal` | Operación que anula otra con los movimientos opuestos y los códigos de reversión. Se ejecuta desde el documento de origen, que corrige su estado; una operación se revierte una sola vez |
| Delta | `delta` | Variación de cantidad de un movimiento: positiva si ingresa, negativa si egresa. Nunca cero |
| Saldo posterior | `balance_after` | Stock físico de la fila inmediatamente después de aplicar el movimiento |
| Libro de inventario | ledger | Conjunto inmutable de operaciones y movimientos. Fuente para reconstruir la historia |
| Transferencia | `transfer` | Operación que traslada stock entre dos ubicaciones del mismo depósito: un movimiento negativo en origen y uno positivo en destino |
| Ajuste | `adjustment_in`, `adjustment_out` | Operación que corrige el stock físico por un motivo registrado |
| Motivo | `reason_code` | Causa de un ajuste, de una lista cerrada: daño, pérdida, hallazgo, corrección |
| Reversión | `reversal` | Operación que compensa otra operación con los deltas opuestos. Es la única forma de corregir una operación confirmada |
| Reserva | `StockReservation` | Compromiso de una cantidad de una fila de existencia para una línea de pedido. Estados: activa, consumida, liberada |
| Bloqueo de stock | `StockBlock` | Inmovilización de una cantidad dentro de una ubicación, sin moverla. Distinto del bloqueo de ubicación |
| Conciliación | `wms:reconcile` | Verificación de que cada existencia coincide con la suma de sus movimientos y que lo reservado coincide con las reservas activas |

## Abastecimiento y recepción

| Término | Código | Definición |
| - | - | - |
| Orden de compra | `PurchaseOrder` | Documento que solicita productos a un proveedor. No modifica stock |
| Línea de orden | `PurchaseOrderItem` | Producto y cantidad solicitada dentro de una orden |
| Recepción | `GoodsReceipt` | Registro de la mercadería que llegó físicamente. Al confirmarse genera la operación de inventario |
| Recepción parcial | estado `partially_received` | Recepción que no completa lo pedido; la orden mantiene el saldo pendiente |
| Recepción ciega | recepción sin orden | Recepción registrada sin orden de compra asociada; requiere permiso específico |
| Rechazo | `quantity_rejected` | Cantidad recibida que no ingresa al stock por defecto o error, con motivo |
| Tolerancia de sobre recepción | `over_receipt_tolerance_pct` | Porcentaje por encima de lo pedido que se acepta sin autorización especial |
| Ubicación definitiva | putaway | Traslado de la mercadería desde el muelle de recepción a una ubicación de almacenamiento |

## Pedidos y despacho

| Término | Código | Definición |
| - | - | - |
| Pedido | `CustomerOrder` | Solicitud de salida de mercadería para un cliente |
| Asignación | allocation | Elección de las filas de existencia concretas desde las que se atenderá un pedido, criterio FIFO |
| Tarea de picking | `PickingTask` | Lista de líneas a recolectar para un pedido, ordenada por ubicación |
| Picking | `pick` | Recolección de una línea: operación que traslada la mercadería de almacenamiento a la ubicación de packing |
| Faltante | `short` | Cantidad que el operador no encontró al pickear. Libera la reserva y dispara un conteo cíclico |
| Packing | `Package` | Verificación y embalaje de lo pickeado en uno o más bultos |
| Bulto | `Package` | Unidad de embalaje con su contenido y peso |
| Despacho | `Dispatch` | Salida de la mercadería del depósito. Documento propio (`DS`) cuya operación `dispatch` (601) tiene delta negativo en packing y consume la reserva. Se revierte con 602 si la mercadería no salió físicamente |

## Inventario físico

| Término | Código | Definición |
| - | - | - |
| Inventario físico | `Stocktake` | Sesión de conteo sobre un conjunto de ubicaciones |
| Conteo ciego | `is_blind` | Conteo en el que el operador no ve la cantidad registrada en el sistema |
| Cantidad de sistema | `system_quantity` | Stock físico registrado al iniciar el conteo |
| Reconteo | `recount_quantity` | Segundo conteo, realizado por otro operador, cuando la diferencia supera el umbral |
| Diferencia | calculada | Cantidad contada menos cantidad de sistema |
| Conteo cíclico | cycle count | Conteo acotado a pocas ubicaciones, por ejemplo tras un faltante de picking |
| Exactitud de inventario | IRA | Porcentaje de ubicaciones contadas sin diferencia |

## Seguridad y trazabilidad

| Término | Código | Definición |
| - | - | - |
| Rol | `Role` | Conjunto de permisos: administrador, supervisor, operador, compras, auditor |
| Permiso | `Permission` | Autorización para una acción, con nombre `modulo.accion` (por ejemplo `inventory.adjust`) |
| Segregación de funciones | regla | Quien ejecuta una acción no puede aprobarla (orden de compra, inventario físico) |
| Auditoría | `AuditLog` | Registro inmutable de quién hizo qué, cuándo, sobre qué entidad y con qué valores antes y después |
| Clave de idempotencia | `Idempotency-Key` | Identificador que el cliente envía con una operación para que sus reintentos no la dupliquen |

## Términos futuros

| Término | Código | Definición |
| - | - | - |
| Lote | `Lot` | Conjunto de unidades de un producto con origen y vencimiento comunes. El modelo lo admite (`stocks.lot_id`), la funcionalidad no está en el alcance inicial |
| FEFO | first expired, first out | Criterio de asignación por vencimiento más próximo; requiere lotes |
