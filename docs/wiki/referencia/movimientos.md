# Movimientos

Todo cambio de stock queda registrado como una **operación**, y cada operación tiene sus **movimientos**. Un movimiento es una línea de la operación: un artículo, una ubicación, la cantidad que entra o sale y su **código de movimiento**, un número de tres dígitos que indica qué originó el cambio. Por ejemplo, una carga inicial de 500 unidades del artículo `TOR-M8` en la ubicación `A-01-01` es un movimiento 561 de entrada por 500.

Los movimientos no se modifican ni se borran. Un error se corrige con una **reversión**: una operación nueva cuyos movimientos devuelven el stock con el código de reversión del original. Una reversión puede cubrir toda la cantidad o una parte; la parte que queda sin revertir sigue vigente. El movimiento que genera una reversión se llama **anulación**, y por eso su texto empieza con **Anulación de …**. La excepción son los códigos 711 y 712, que se revierten entre sí: una salida 711 se revierte con una entrada 712, y al revés.

Esta página lista los códigos de las operaciones disponibles hoy. Los de recepción, guardado, picking, despacho e inventario físico se suman con esas funciones.

| Código | Dirección | Texto | Lo genera | Se revierte con |
| - | - | - | - | - |
| [931](#mov-931) | Salida y entrada | Transferencia, salida / Transferencia, entrada | Transferencia de stock | 932 |
| [932](#mov-932) | Entrada y salida | Anulación de transferencia, salida / Anulación de transferencia, entrada | Reversión de una transferencia | 931 |
| [551](#mov-551) | Salida | Baja por daño | Ajuste negativo por daño | 552 |
| [552](#mov-552) | Entrada | Anulación de baja por daño | Reversión de una baja por daño | 551 |
| [711](#mov-711) | Salida | Diferencia manual, salida | Ajuste negativo por pérdida o por corrección | 712 |
| [712](#mov-712) | Entrada | Diferencia manual, entrada | Ajuste positivo por sobrante o por corrección | 711 |
| [561](#mov-561) | Entrada | Carga inicial de stock | Carga inicial de stock | 562 |
| [562](#mov-562) | Salida | Anulación de carga inicial | Reversión de una carga inicial | 561 |

## Transferencias {#transferencias}

### 931 Transferencia {#mov-931}

Mueve stock de una ubicación a otra del mismo depósito. Cada posición de la transferencia genera dos movimientos 931 por la misma cantidad: una salida de la ubicación de origen y una entrada en la de destino. El destino no puede ser una ubicación de sistema (RECEPCION, PACKING o DESPACHO) ([`SYSTEM_LOCATION_NOT_ALLOWED`](./mensajes#system-location-not-allowed)).

### 932 Anulación de transferencia {#mov-932}

Revierte una transferencia: devuelve el stock de la ubicación de destino a la de origen. Los dos movimientos de una posición se revierten juntos y por la misma cantidad.

## Ajustes {#ajustes}

Un **ajuste** corrige el stock registrado para que coincida con el real, con un motivo que decide el código. Un ajuste positivo no puede tener como destino una ubicación de sistema; uno negativo sí, porque registra una pérdida real. Una posición que supera el umbral de aprobación de su artículo no genera movimientos hasta que otro usuario la aprueba ([Gestionar umbrales de ajuste](/administracion/umbrales-de-ajuste)).

### 551 Baja por daño {#mov-551}

Ajuste negativo por mercadería dañada.

### 552 Anulación de baja por daño {#mov-552}

Revierte una baja por daño y devuelve el stock a la ubicación.

### 711 Diferencia manual, salida {#mov-711}

Ajuste negativo por pérdida o por corrección. También revierte un movimiento 712.

### 712 Diferencia manual, entrada {#mov-712}

Ajuste positivo por un sobrante encontrado o por corrección. También revierte un movimiento 711.

## Carga inicial {#carga-inicial}

### 561 Carga inicial de stock {#mov-561}

Registra el stock que ya existe físicamente cuando se empieza a usar el sistema en un depósito o en un sector nuevo. Una carga puede tener varias posiciones, cada una con su artículo, su ubicación y su cantidad, y se registra completa o no se registra. No pasa por el umbral de aprobación: la puede hacer solo quien tiene el permiso [`inventory.initial_load`](./permisos#inventory-initial-load), en los depósitos donde opera. Va solo en ubicaciones de almacenamiento, nunca en una de sistema ([`SYSTEM_LOCATION_NOT_ALLOWED`](./mensajes#system-location-not-allowed)), y suma al stock que la ubicación ya tenga.

### 562 Anulación de carga inicial {#mov-562}

Revierte una carga inicial, por toda la cantidad o por una parte. Sirve para corregir una carga mal hecha: se revierte y se vuelve a cargar con los valores correctos.
