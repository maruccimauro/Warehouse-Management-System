# La pantalla principal

La pantalla principal tiene un encabezado con el campo de comando y la barra de herramientas, el menú de transacciones a la izquierda, el área de trabajo en el centro y la barra de estado abajo.

## Transacción {#transaccion}

Una **transacción** es una pantalla con un código fijo. `SU3` abre los parámetros de usuario. Cada transacción tiene su propia dirección (`/t/SU3`), así que el botón Atrás del navegador, los marcadores y las pestañas funcionan como en cualquier sitio.

La lista completa está en la [referencia de transacciones](/referencia/transacciones).

## Menú de transacciones {#menu}

El menú agrupa las transacciones en carpetas por área funcional. Solo muestra las transacciones que los roles del usuario permiten abrir; una carpeta sin transacciones permitidas no aparece.

- **Abrir una transacción**: clic en ella, o <kbd>Enter</kbd> con el foco sobre ella.
- **Moverse con el teclado**: <kbd>↑</kbd> y <kbd>↓</kbd> recorren los ítems visibles, <kbd>→</kbd> expande una carpeta o entra en ella, <kbd>←</kbd> la contrae o sube a la carpeta superior, <kbd>Inicio</kbd> y <kbd>Fin</kbd> van al primero y al último.
- **Menú contextual**: clic secundario, o <kbd>Mayús</kbd>+<kbd>F10</kbd>, sobre un ítem: **Ejecutar**, **Abrir en ventana nueva**, **Agregar a favoritos** o **Quitar de favoritos**, **Expandir** o **Contraer**, **Expandir todo**, **Contraer todo** y **Mostrar códigos de transacción**.

En pantallas angostas el menú se oculta; el botón **Menú** del encabezado lo muestra y lo oculta.

### Favoritos {#favoritos}

La carpeta **Favoritos**, arriba del menú, reúne las transacciones de uso frecuente. Se agrega una transacción desde el menú contextual o, con la transacción abierta, con el botón **Favorito** de la barra de herramientas. Los favoritos se guardan en el servidor y acompañan al usuario en cualquier equipo. Se admiten hasta 50.

### Mostrar códigos {#mostrar-codigos}

La casilla **Mostrar códigos de transacción**, al pie del menú y también en su menú contextual, agrega el código delante del título de cada transacción (`SU3 Parámetros de usuario`). La preferencia se guarda con el usuario.

## Campo de comando {#campo-de-comando}

El campo a la izquierda del encabezado abre transacciones por su código. Se escribe el código y se confirma con <kbd>Enter</kbd> o con el botón de confirmar.

| Comando | Efecto |
| - | - |
| `SU3` | Abre la transacción en esta ventana, también desde dentro de otra |
| `/nSU3` | Sale de la transacción actual y abre `SU3` |
| `/oSU3` | Abre `SU3` en una ventana nueva |
| `/n` | Sale de la transacción actual y vuelve al menú |
| `/o` | Abre el menú en una ventana nueva |

Mayúsculas y minúsculas son indistintas. Al escribir, el campo sugiere las transacciones usadas antes y luego las demás permitidas.

Una transacción inexistente y una sin autorización dan mensajes distintos en la barra de estado: **La transacción ZZ01 no existe.** o **No tiene autorización para la transacción ZZ01.**

## Barra de herramientas estándar {#barra-de-herramientas}

| Botón | Tecla | Acción |
| - | - | - |
| **Grabar** | <kbd>Ctrl</kbd>+<kbd>S</kbd> | Graba los datos de la pantalla |
| **Atrás** | <kbd>F3</kbd> | Vuelve a la pantalla anterior de la transacción o al menú |
| **Finalizar** | <kbd>Mayús</kbd>+<kbd>F3</kbd> | Sale de la transacción y vuelve al menú |
| **Ejecutar** | <kbd>F8</kbd> | Ejecuta una pantalla de selección |
| **Favorito** | | Agrega o quita la transacción abierta de los favoritos |
| **Ayuda** | <kbd>F1</kbd> | Abre la ayuda del campo con foco en esta wiki ([ayuda](#ayuda)) |

Cada transacción habilita solo los botones que admite: una pantalla sin nada que grabar deja **Grabar** deshabilitado. El menú contextual del área de trabajo (clic secundario, o <kbd>Mayús</kbd>+<kbd>F10</kbd>) ofrece las mismas funciones.

## Barra de estado {#barra-de-estado}

La barra inferior informa el resultado de cada acción con un mensaje de uno de cuatro tipos:

| Tipo | Significado |
| - | - |
| S | Éxito: la acción se completó |
| W | Advertencia: se completó con una observación |
| E | Error: la acción no se realizó |
| I | Información |

Los errores muestran su código entre paréntesis, por ejemplo `(WAREHOUSE_INACTIVE)`. El código es un enlace a su explicación en la [referencia de errores](/referencia/errores).

A la derecha, la barra muestra la transacción abierta, el **Depósito por defecto** (el parámetro [`LGN`](./parametros-de-usuario#lgn)), el usuario y el idioma.

## Ayuda {#ayuda}

<kbd>F1</kbd> abre esta wiki en la página que explica lo que tiene el foco:

1. Con el foco en un campo, la explicación de ese campo en la página de la transacción.
2. Si el campo no tiene una explicación propia, la página de la transacción.
3. En el menú, la página de inicio de la wiki.

La ayuda se abre en una pestaña aparte, que se reutiliza en cada consulta: la transacción queda como estaba, sin perder lo escrito. <kbd>F1</kbd> también funciona dentro de un diálogo, como la ayuda de búsqueda.

El botón **Ayuda** de la barra de herramientas y la opción **Ayuda** del menú contextual del área de trabajo hacen lo mismo: el botón usa el último campo que tuvo el foco y el menú contextual, el lugar donde se hizo el clic secundario.

## Datos sin grabar {#datos-sin-grabar}

Si la transacción tiene cambios sin grabar, toda salida pide confirmación: <kbd>F3</kbd>, <kbd>Mayús</kbd>+<kbd>F3</kbd>, `/n`, el menú, el botón Atrás del navegador y **Cerrar sesión**. El diálogo **Salir sin grabar** ofrece dos opciones:

- **Seguir editando**, que tiene el foco al abrir: vuelve a la pantalla sin perder nada.
- **Salir sin grabar**: descarta los cambios y sale.

Al cerrar o recargar la pestaña, el aviso lo muestra el propio navegador. `/o` abre otra ventana y no pide confirmación, porque la transacción actual sigue abierta.
