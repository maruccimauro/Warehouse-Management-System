# ADR-016: Interfaz inspirada en SAP GUI

- Estado: Propuesto
- Fecha: 23/11/2026
- Relacionados: ADR-009, ADR-010, ADR-014

## Contexto

SAP es la referencia funcional del sistema (movimientos, reversiones y estados de documentos, ADR-014). Quien opera un depósito con SAP trabaja con un conjunto de mecánicas de interfaz que resuelven bien el uso intensivo con teclado: un árbol de transacciones, códigos para abrir cada pantalla sin pasar por el menú, grillas de datos con orden, filtros y totales, ayudas de búsqueda sobre cada campo y una barra de estado que informa el resultado de cada acción.

La interfaz necesita una dirección visual y de interacción común antes de que E2 agregue las primeras pantallas de datos maestros. Sin ella, cada pantalla resuelve por su cuenta la navegación, las tablas y la selección de valores.

## Decisión

### Mecánicas que se toman de SAP GUI

| Pieza | En SAP | En el WMS |
| - | - | - |
| Árbol de transacciones | SAP Easy Access, con carpeta de favoritos y la opción de mostrar nombres técnicos | Panel lateral con el árbol por área funcional, favoritos y opción de mostrar los códigos. Desde E2 se filtra por los permisos del usuario |
| Campo de comando | Código de transacción, `/nXXXX` en la misma ventana, `/oXXXX` en una ventana nueva, `/n` para salir de la transacción | Las mismas formas, con historial y sugerencias. Desviación: un código sin prefijo abre la transacción también desde dentro de otra, sin exigir `/n` |
| Rutas | No aplica | Cada transacción es una ruta (`/t/MIGO`): el botón atrás del navegador, los enlaces y las pestañas funcionan |
| Barra de herramientas estándar | Confirmar, grabar, atrás, finalizar, cancelar | Confirmar, grabar, atrás, finalizar y agregar a favoritos. Cada transacción habilita las acciones que admite |
| Barra de estado | Mensajes tipo S (éxito), W (advertencia), E (error) e I (información), con clase y número de mensaje | Mismos tipos. El identificador del mensaje es el `code` del Problem Details (ADR-010). A la derecha muestra transacción, depósito por defecto, usuario e idioma |
| Parámetros de usuario | SU3, con IDs de parámetro (`LGN` número de almacén, `WRK` centro) que completan los campos de las pantallas iniciales | Transacción `SU3` con el idioma (valores fijos), el parámetro `LGN` (depósito por defecto) y el cambio voluntario de contraseña. Desde E2.21 parámetros, favoritos, idioma y mostrar códigos se guardan en el servidor (DM-24); el historial del campo de comando queda en el navegador, como en SAP GUI. El selector ofrece solo los idiomas que la interfaz tiene traducidos |
| Selección de depósito | Campo en la pantalla inicial de cada transacción, completado desde el parámetro, con F4 | Igual: no existe un depósito activo global. Cada pantalla muestra el depósito sobre el que opera y F4 ofrece solo los asignados al usuario (REG-005) |
| F4 | Ayuda de búsqueda con criterios y lista de resultados | Componente genérico de campo con ayuda de búsqueda (E2.17): depósito, producto, ubicación, código de movimiento; ver "Ayuda de búsqueda (F4)" |
| ALV | Grilla con orden, filtro, totales, subtotales, selección de columnas, layouts y exportación | Grilla sobre TanStack Table 9 (E2.00), con modo servidor y modo local; ver "Grilla ALV". Los layouts guardados quedan para después del MVP |
| Pantalla de selección | Campos con desde/hasta, selección múltiple, exclusiones y variantes | Campos con rango y selección múltiple (E2.18). Exclusiones y variantes, después del MVP |
| F1 | Ayuda del campo | Enlace a la wiki con claves de ayuda (E2.16) |
| Menú contextual | Clic secundario en el árbol (ejecutar, agregar a favoritos) y en la pantalla (funciones disponibles con su tecla) | Igual en el árbol (ejecutar, abrir en ventana nueva, favoritos, expandir y contraer, mostrar códigos) y en el área de trabajo (grabar, atrás, finalizar, ejecutar y favorito, con la disponibilidad de la barra). Se abre también con Mayús+F10 o la tecla de menú. Los campos editables, los enlaces del área de trabajo y el texto seleccionado conservan el menú del navegador. Toda acción del menú existe también en otro control, así que no hace falta un botón visible para pantallas táctiles. Desde E2.00 la grilla suma el suyo (ordenar, filtrar, columnas) |

### Teclas

| Tecla | Acción | Nota |
| - | - | - |
| Enter | Confirmar | En el campo de comando ejecuta el código |
| F1 | Ayuda | Desde E2.16 |
| F3 | Atrás | El navegador usa F3 para buscar; se intercepta |
| Mayús+F3 | Finalizar: vuelve al menú | |
| F4 | Ayuda de búsqueda del campo | Desde E2.17 |
| F8 | Ejecutar | En pantallas de selección |
| Ctrl+S | Grabar | Se intercepta el guardado de página del navegador |

Con un popup modal abierto (F4, filtro de la grilla, datos sin grabar) F3, Mayús+F3, F8 y Ctrl+S no actúan sobre la pantalla de abajo; F3 y Ctrl+S igual se cancelan para el navegador.

F5, F11 y F12 no se interceptan: recargar, pantalla completa y herramientas de desarrollo son del navegador y no se pueden capturar de forma confiable. Cancelar (F12 en SAP) queda como botón.

### Registro de transacciones y acceso

Desde E2.20 cada transacción del registro (`frontend/src/app/transactions.ts`) declara el permiso de negocio que exige abrirla (`catalog.manage` para `MM01`, por ejemplo) o `null` si basta con tener sesión. No se usa un permiso propio por transacción como el objeto `S_TCODE` de SAP: en SAP abrir la pantalla y operar en ella son dos autorizaciones, lo que permite la combinación de abrir `MM01` y recibir un rechazo en cada grabación. Con un único permiso, lo que la interfaz muestra coincide con lo que el backend deja hacer.

- El árbol, las sugerencias del campo de comando y los favoritos muestran solo las transacciones permitidas según `me.permissions`; una carpeta sin transacciones permitidas no se muestra. Un favorito sin autorización se conserva por si el permiso vuelve.
- Como en SAP, una transacción inexistente y una sin autorización dan mensajes distintos en la barra de estado (tipo E). Una ruta `/t/CÓDIGO` sin autorización vuelve al menú con ese mensaje. Ocultar es usabilidad: el backend autoriza cada operación.
- Cada transacción se carga en diferido (`React.lazy` con `import()`) y las dependencias van en chunks propios (`codeSplitting` de Rolldown en `vite.config.ts`), que el navegador conserva en caché entre despliegues. Un chunk que no carga, por ejemplo en una pestaña abierta durante un despliegue, muestra un error con la opción de recargar sin romper el armazón.

### Datos sin grabar

Equivale a `POPUP_TO_CONFIRM_LOSS_OF_DATA` de SAP. La transacción declara si tiene cambios (`useUnsavedChanges`) y el armazón intercepta toda salida: F3, Mayús+F3, `/n`, el árbol, el botón Atrás del navegador (`useBlocker` del router), el cierre de sesión y el cierre o la recarga de la pestaña (`beforeunload`, con el aviso propio del navegador). El diálogo es un `<dialog>` modal nativo con dos acciones, "Salir sin grabar" y "Seguir editando"; el foco inicial va a la segunda, que es la acción segura. No se ofrece "Grabar y salir" como tercer botón: grabar puede fallar por validación y encadenar grabación y salida suma estados de error sin un formulario que lo necesite todavía. `/o` abre otra ventana y no pasa por el aviso.

### Grilla ALV

Componente `DataGrid` en `frontend/src/shared/ui/grid/` (E2.00), sobre `@tanstack/react-table ~9.3.1`. Las pantallas declaran columnas (`GridColumn`) y una fuente de datos; TanStack Table queda como detalle interno.

- Dos modos. En modo servidor, para los listados de datos maestros, orden, filtro y página se traducen a los parámetros de `ListRequest` (E2.10: `filter[campo]`, `filter[campo][]`, `sort`, `page`, `per_page`), y solo se ofrece ordenar y filtrar por las columnas que el listado del backend declara. En modo local, para el diálogo F4 y los informes con resultado acotado, la grilla ordena y filtra en el cliente, como la ALV sobre la tabla interna completa.
- Totales (Σ por columna) y subtotales (un nivel, por la columna elegida) existen solo en modo local. Sobre una página del servidor un total parece el general y no lo es. Solo se suman las columnas declaradas `summable`: sumar un nivel o un identificador no tiene sentido.
- Filtro como "Definir filtro" de la ALV: un diálogo por columna con el operador que corresponde al modo del backend (contiene, igual a, uno de varios). Los filtros activos quedan visibles sobre la grilla y se quitan de a uno.
- Exportación a CSV como "Exportar a hoja de cálculo": todo el resultado del filtro, con las columnas visibles y en el orden que se ve. En modo servidor recorre las páginas de 200, hasta 5.000 filas; si quedan filas afuera avisa con un mensaje W. Separador según el idioma (punto y coma con coma decimal), BOM UTF-8 y neutralización de fórmulas (un valor que empieza con `=`, `+`, `-`, `@`, tabulación o retorno de carro lleva un apóstrofo adelante). Volúmenes mayores piden un endpoint CSV en el backend, que llega con el libro de movimientos de E3.
- Teclado del patrón Grid de WAI-ARIA: una sola parada de Tab, flechas entre celdas, Inicio y Fin en la fila, Ctrl+Inicio y Ctrl+Fin en la grilla, RePág y AvPág; Enter y doble clic activan la fila (abrir la visualización, tomar el valor de un F4) y Espacio la selecciona cuando la pantalla habilita la selección múltiple. En un borde no se cancela la tecla y el navegador sigue desplazando la página.
- Menú contextual con `ContextMenu` sobre la celda o la cabecera, también con Mayús+F10: ordenar, filtrar, quitar filtro, total y subtotal, ocultar columna y modificar disposición. Las funciones de la barra de la grilla actúan sobre la columna de la celda con foco, como la columna marcada en la ALV.
- La disposición (columnas visibles y su orden) dura lo que la transacción abierta. Su forma (`GridLayout`, con ids de columna) ya admite persistirla; guardarla en el servidor es el layout de SAP y queda para después del MVP.

### Ayuda de búsqueda (F4)

Componente `SearchHelpField` en `frontend/src/shared/ui/searchHelp/` (E2.17). Cada ayuda se declara como `SearchHelp`: columnas de la lista, restricciones, valor que devuelve, texto descriptivo, normalización y mensaje de valor inexistente. `WarehouseField` (`features/warehouses/`) es la primera, sobre `me.warehouses`.

- F4 sin modificadores, o el botón al lado del campo, abre el popup: restricciones arriba que filtran en vivo y la grilla ALV en modo local abajo. Enter o doble clic en una fila toman el valor; Enter en una restricción con un único resultado también; Esc cancela. Al cerrar, el foco vuelve al campo. F4 lo maneja el campo y no el armazón: es la ayuda del campo con foco.
- Como en SAP, lo escrito que no es un valor válido restringe el primer criterio al abrir, y un valor válido abre la lista con el foco en su fila.
- El campo es también la tabla de verificación: normaliza lo escrito (mayúsculas en los códigos), informa valor y validez a la pantalla en cada cambio y, al confirmar con Enter o al salir del campo, marca un valor inexistente y emite un mensaje E. Informar en cada cambio hace que Ctrl+S con el foco en el campo vea lo escrito, como el PAI de SAP; la pantalla no graba con un valor inválido.
- A la derecha del valor se muestra su texto (`CEN  Depósito central`), asociado al campo con `aria-describedby`.
- El botón de la ayuda queda fuera del orden de Tab, como en SAP: con teclado se usa F4 (anunciada con `aria-keyshortcuts`) y Tab pasa al campo siguiente.
- Por ahora solo modo local. Las ayudas sobre miles de filas (producto, ubicación) suman una fuente de servidor cuando llegue la primera pantalla que las use, traduciendo las restricciones a `filter[...]`.
- `useDefaultWarehouse` devuelve el parámetro `LGN` si el depósito sigue entre los que el usuario opera; las pantallas operativas lo usan como valor inicial del campo Depósito.

### Códigos de transacción

Se usa el código de SAP donde la transacción tiene equivalente y el rango `ZW` para las propias, con la misma regla que ADR-014 aplica a los movimientos (`Z` es el espacio de nombres de cliente en SAP). La tabla es indicativa: cada etapa confirma sus códigos al implementar la pantalla y la página de referencia de la wiki documenta en qué se aparta cada una de su par de SAP.

| Etapa | Código | Transacción | Equivalente SAP |
| - | - | - | - |
| E1 | `SU3` | Parámetros de usuario | SU3 |
| E2 | `SU01` | Usuarios | SU01 |
| E2 | `PFCG` | Roles y permisos | PFCG |
| E2 | `ZW01` | Depósitos | Configuración (sin transacción de usuario) |
| E2 | `ZW02` | Zonas | Tipos de almacén en configuración |
| E2 | `LS01N`, `LS02N`, `LS03N` | Crear, modificar y visualizar ubicación | LS01N, LS02N, LS03N |
| E2 | `MM01`, `MM02`, `MM03` | Crear, modificar y visualizar producto | MM01, MM02, MM03 |
| E2 | `XK01`, `XK02`, `XK03` | Crear, modificar y visualizar proveedor | XK01 a XK03 (BP en S/4HANA) |
| E3 | `MMBE` | Resumen de stock | MMBE |
| E3 | `MB51` | Lista de documentos de material | MB51 |
| E3 | `LT10` | Transferencia entre ubicaciones | LT10 |
| E3 | `ZW03` | Ajuste de stock | Sin transacción directa (diferencias por MI07) |
| E4 | `ME21N`, `ME22N`, `ME23N` | Crear, modificar y visualizar orden de compra | ME21N a ME23N |
| E4 | `MIGO` | Entrada de mercadería | MIGO |
| E4 | `LT06` | Ubicación de lo recibido | LT06 |
| E5 | `VA01`, `VA02`, `VA03` | Crear, modificar y visualizar pedido | VA01 a VA03 |
| E5 | `LT03` | Picking de un pedido | LT03 |
| E5 | `ZW04` | Empaque | Embalaje en VL02N |
| E5 | `VL02N`, `VL09` | Despacho y su reversión | VL02N, VL09 |
| E6 | `MI01`, `MI04`, `MI07`, `MI20` | Inventario físico: documento, conteo, contabilización de diferencias, lista de diferencias | MI01, MI04, MI07, MI20 |
| E7 | `MB52`, `MB5B` | Stock por depósito y stock a una fecha | MB52, MB5B |

### Apariencia

- Tailwind CSS 4 con los tokens del tema en `@theme` (colores, densidad, alturas de fila) y componentes base que concentran las clases.
- Densidad alta: filas de 24 a 28 px y campos compactos, con objetivos táctiles de al menos 24 px y mayores con puntero grueso.
- Paleta propia en la línea del tema claro de SAP y fuente IBM Plex Sans (OFL-1.1). No se usan el nombre, el logo ni la fuente "72" de SAP: la interfaz se inspira en las mecánicas, no reproduce la marca.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| SAP Fiori con UI5 Web Components (Apache 2.0) | Fiori organiza el acceso en un launchpad de tiles, sin árbol ni códigos de transacción, que son lo que se busca. La biblioteca es pesada y su sistema de temas no convive bien con Tailwind |
| Selector global de depósito activo en el encabezado | El contexto queda fuera de la pantalla: quien cambia de depósito y lo olvida registra operaciones en el depósito equivocado. Tampoco permite reportes sobre varios depósitos |
| Códigos propios para todas las transacciones | Pierden el reconocimiento inmediato de quien viene de SAP |
| Biblioteca de componentes genérica (MUI, Mantine) | Aspecto genérico y acoplamiento a su sistema de temas; no aporta árbol de transacciones, ALV ni ayudas de búsqueda con el comportamiento de SAP |
| CSS propio sin framework | Viable, pero Tailwind 4 resuelve los tokens y la densidad con menos código propio |

## Consecuencias

- Toda pantalla nueva es una transacción registrada con código, título, carpeta del árbol, clave de ayuda y, desde E2, permiso requerido.
- Las pantallas de E2 en adelante usan los componentes de E2.00, E2.17 y E2.18 (grilla, campo con F4, pantalla de selección) y no tablas ni selectores propios.
- El depósito es un campo de cada pantalla operativa; las consultas del frontend incluyen el depósito en su clave de caché.
- Los parámetros de usuario, los favoritos, el idioma y mostrar códigos se guardan en el servidor desde E2.21 (DM-24). Lo guardado en el navegador en E1 se sube una sola vez: cada preferencia solo completa lo que el servidor tiene vacío.
- Una contraseña inicial o reseteada reemplaza el armazón por la pantalla de cambio, sin ruta propia, como el diálogo de clave inicial de SAP (REG-021). Un 403 `PASSWORD_CHANGE_REQUIRED` a mitad de la sesión vuelve a esa pantalla.
- Pasa a Aceptado al cerrar E2.00, E2.17 y E2.18, con lo aprendido al construir la grilla y las ayudas de búsqueda.
