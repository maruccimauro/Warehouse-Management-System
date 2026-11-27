# Bandeja de entrada (SBWP)

La transacción `SBWP` reúne los **avisos** que el sistema le deja al usuario: mensajes sobre hechos que le corresponde conocer, como un cambio que hizo otro usuario y que le corresponde controlar. Cualquier usuario con sesión la puede abrir; cada uno ve solo sus propios avisos. Está en el menú en la carpeta **Sistema**, y también se abre desde el [contador del encabezado](#contador).

## Contador de no leídos {#contador}

A la izquierda del nombre del usuario, en el encabezado, el ícono de la bandeja muestra cuántos avisos no leídos hay en la carpeta **Entrada**. Sin avisos no leídos, el ícono se muestra sin número. Un clic en él abre la bandeja.

El contador se actualiza solo, en menos de un minuto, y también al volver a la pestaña del navegador.

## Carpetas {#carpetas}

| Carpeta | Contenido |
| - | - |
| **Entrada** | Los avisos sin archivar, leídos y no leídos |
| **Archivados** | Los avisos que el usuario archivó |

Se cambia de carpeta con un clic en su pestaña, o con <kbd>←</kbd> y <kbd>→</kbd> con el foco en las pestañas.

## Lista de avisos {#lista}

La lista muestra los avisos de la carpeta, del más nuevo al más viejo, con las columnas **Recibido** (fecha y hora), **Mensaje** y **Estado** (**No leído** o **Leído**). Los avisos no leídos van en negrita. La lista tiene las funciones de cualquier [lista](./listas): disposición, exportación y paginación.

Doble clic o <kbd>Enter</kbd> sobre un aviso lo marca como leído y, si el aviso se refiere a un registro (un usuario, por ejemplo), abre la transacción que lo muestra.

## Marcar y archivar {#acciones}

Las acciones de la barra de la transacción se aplican a los avisos marcados con la casilla de la primera columna, de la página visible. La casilla del título marca todos los de la página.

| Acción | Carpeta | Efecto |
| - | - | - |
| **Marcar como leído** | Entrada | Los avisos quedan leídos y dejan de sumar en el contador |
| **Marcar como no leído** | Entrada | Los avisos vuelven a sumar en el contador |
| **Archivar** | Entrada | Los avisos pasan a **Archivados** y quedan leídos |
| **Desarchivar** | Archivados | Los avisos vuelven a **Entrada**, leídos |

La barra de estado confirma cuántos avisos cambiaron (**2 mensajes archivados.**). Si la marca no se pudo aplicar, la barra de estado muestra el error y ningún aviso cambia.

::: info
Los avisos archivados se borran a los 90 días de archivados ([retención de la bandeja](/referencia/reglas-de-negocio#retencion-de-la-bandeja)). Los de la carpeta **Entrada** se conservan hasta que el usuario los archiva.
:::
