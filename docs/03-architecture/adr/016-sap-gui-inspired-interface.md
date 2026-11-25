# ADR-016: Interfaz inspirada en SAP GUI

- Estado: Aceptado
- Fecha: 23/11/2026 (aceptado el 24/11/2026, al cerrar E2.00, E2.17 y E2.18)
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
| Pantalla de selección | Campos con desde/hasta, selección múltiple, exclusiones y variantes | Parámetros y criterios con rango y selección múltiple (E2.18); ver "Pantalla de selección". El modelo y la API ya admiten exclusiones; su edición en pantalla y las variantes, después del MVP |
| F1 | Ayuda del campo (Performance Assistant), en una ventana que se reutiliza | La wiki de usuario en una pestaña con nombre fijo que se reutiliza, en la explicación del campo con foco, de la transacción o en el inicio (E2.16); ver "Ayuda F1" |
| Menú contextual | Clic secundario en el árbol (ejecutar, agregar a favoritos) y en la pantalla (funciones disponibles con su tecla) | Igual en el árbol (ejecutar, abrir en ventana nueva, favoritos, expandir y contraer, mostrar códigos) y en el área de trabajo (grabar, atrás, finalizar, ejecutar y favorito, con la disponibilidad de la barra). Se abre también con Mayús+F10 o la tecla de menú. Los campos editables, los enlaces del área de trabajo y el texto seleccionado conservan el menú del navegador. Toda acción del menú existe también en otro control, así que no hace falta un botón visible para pantallas táctiles. Desde E2.00 la grilla suma el suyo (ordenar, filtrar, columnas) |

### Teclas

| Tecla | Acción | Nota |
| - | - | - |
| Enter | Confirmar | En el campo de comando ejecuta el código |
| F1 | Ayuda | También con un popup abierto. Se intercepta: el navegador no abre su propia ayuda |
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

### Alta, modificación, visualización y baja

Criterio decidido el 25/11/2026 (E2.24, opción 1 del usuario), escrito para que cada objeto nuevo siga el mismo patrón:

- Los datos maestros con volumen y con permisos distintos para consultar y editar (catálogo, proveedores, ubicaciones, usuarios) llevan el trío **Crear**, **Modificar** y **Visualizar** (las actividades 01, 02 y 03 de SAP), con pantalla inicial y ficha (`useInitialScreenFlow`), más su **Lista**. Visualizar no exige permiso de edición.
- Las tablas de configuración chicas (depósitos, zonas, categorías, unidades de medida) y los vínculos (catálogo del proveedor) llevan una transacción de gestión **Gestionar <objetos>**, con lista y ficha en visualizar, el botón Modificar y Crear en la barra de aplicación, como las vistas de tabla de SAP (SM30), y además, como en SAP, una transacción propia para cada acción: crear, modificar y visualizar (corrección del usuario del 25/11/2026: la gestión puede reunir varias acciones, pero cada acción tiene su transacción). Las individuales usan la misma ficha que la gestión, sin el paso de visualizar a modificar, y al grabar vuelven a su pantalla inicial.
- Un maestro con trío también puede tener su gestión, cuando conviene reunir las tres acciones en una pantalla para quien administra: **Gestionar <objetos>** con pantalla inicial (el registro) y **Crear**, **Modificar** y **Visualizar** en la barra de aplicación, como SU01 de SAP. Crear toma el código escrito y verifica que no exista; desde visualizar se pasa a modificar. Las individuales siguen existiendo. Primer caso: usuarios (`SU01` con `ZW91` a `ZW93`, E2.14), porque la ficha se mantiene con frecuencia y el código de usuario se elige en el alta.
- Códigos del rango `ZW` para esas familias: `ZW0N` es la gestión y `ZWN1`, `ZWN2` y `ZWN3` son crear, modificar y visualizar, como el sufijo de actividad 01, 02 y 03 de SAP (`ZW01` y `ZW11` a `ZW13` para depósitos, `ZW02` y `ZW21` a `ZW23` para zonas, `ZW06` y `ZW61` a `ZW63` para el catálogo del proveedor, `ZW07` y `ZW71` a `ZW73` para categorías, `ZW08` y `ZW81` a `ZW83` para unidades de medida). Cuando la gestión tiene código de SAP (`SU01`), las individuales toman una familia libre del rango sin usar su `ZW0N`: `ZW91` a `ZW93` para usuarios, y `ZW09` queda sin asignar.
- Las bajas son lógicas: un registro con historia se desactiva (o se discontinúa) desde Modificar o desde la ficha, con confirmación, y no se borra. Un bloqueo operativo que maneja otro permiso tiene transacción propia (`ZW05`, `locations.block`).
- El identificador de negocio de cada maestro es propio y estable, nunca el `id` técnico: código en depósitos, zonas y ubicaciones, SKU en el catálogo, número interno asignado por una secuencia en proveedores (`suppliers_number_seq`, mostrado con seis dígitos). Las categorías no tienen código: se identifican por su ruta desde el primer nivel (`Ferretería / Tornillería`), porque el nombre solo es único entre hermanas; la ruta cambia si la categoría se renombra o se mueve, y las pantallas la recalculan desde la lista completa, que es chica.

### Datos sin grabar

Equivale a `POPUP_TO_CONFIRM_LOSS_OF_DATA` de SAP. La transacción declara si tiene cambios (`useUnsavedChanges`) y el armazón intercepta toda salida: F3, Mayús+F3, `/n`, el árbol, el botón Atrás del navegador (`useBlocker` del router), el cierre de sesión y el cierre o la recarga de la pestaña (`beforeunload`, con el aviso propio del navegador). El diálogo es un `<dialog>` modal nativo con dos acciones, "Salir sin grabar" y "Seguir editando"; el foco inicial va a la segunda, que es la acción segura. No se ofrece "Grabar y salir" como tercer botón: grabar puede fallar por validación y encadenar grabación y salida suma estados de error sin un formulario que lo necesite todavía. `/o` abre otra ventana y no pasa por el aviso. Desde E2.12, F3 con un Atrás propio de la transacción (de la ficha a la lista o a la pantalla inicial) también pasa por el aviso: descarta lo editado igual que salir al menú.

### Ayuda F1

La wiki de usuario (`docs/wiki/`, VitePress, servida en `/docs/` del mismo host) es la única fuente de las explicaciones (DM-11, E2.15 y E2.16). `frontend/src/shared/help/helpLinks.ts` declara las claves tipadas (`HelpKey`) y su destino `página#ancla`; las anclas son explícitas (`{#...}`) para que no cambien con el título.

- F1, el botón Ayuda de la barra estándar y el menú contextual del área de trabajo resuelven la clave desde un elemento, de lo más específico a lo más general: el `data-help-key` explícito más cercano, el campo de la transacción (`data-help-field`, que ponen `Field` de los bloques de formulario y las filas de la pantalla de selección, con la clave `field.<código>.<campo>`), el `helpKey` de la transacción en el registro y el inicio de la wiki. El botón usa el último elemento enfocado del área de trabajo y el menú, el del clic secundario.
- Como el elemento de datos de SAP no existe acá, la ayuda de un campo se escribe en la página de cada transacción que lo usa, con un ancla por campo, aunque el campo se repita en otras pantallas.
- La ayuda abre en la pestaña con nombre `wms-help`, que se reutiliza como la ventana del Performance Assistant; la transacción conserva su estado. No se usa `noopener`: el nombre de la ventana es lo que permite reutilizarla y la wiki es propia y del mismo origen.
- El código de un mensaje de la barra de estado es un enlace a `referencia/mensajes#<code>` cuando la wiki lo documenta, como el texto largo del mensaje que SAP abre con doble clic (clave `message.<CODE>`). Desde E2.12 la página se llama Mensajes y no Errores: también los mensajes E y W que emite el cliente antes de llamar al servidor llevan código y explicación (`REQUIRED_FIELDS_MISSING`, `VALUE_NOT_FOUND`, `GRID_EXPORT_TRUNCATED`, `LOCATION_NOT_FOUND`, `LOCATION_BATCH_TOO_LARGE`, entre otros), para que el usuario sepa qué pasó, por qué y cómo seguir. `HelpLink` es el mismo enlace para estados y permisos en las pantallas.
- `docs/wiki/scripts/check-help-links.ts` importa `helpLinks.ts` con Node 26, sin compilar, y falla si una clave no tiene su página o su ancla; corre en el job Wiki del CI junto con el build, que falla con enlaces rotos entre páginas.

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
- Fuente local o de servidor (E2.12). La local verifica lo escrito contra sus filas; si las filas todavía se están leyendo (`loading`), no rechaza el valor y lo verifica al llegar. La de servidor, para las ayudas sobre miles de filas (ubicación, producto), traduce las restricciones a filtros del listado (`filtersOf`, la ubicación con `CP *fragmento*`) y pagina en el servidor; no tiene tabla de verificación en el campo: la pantalla valida el valor al leer el registro, como la pantalla inicial de una transacción de modificar o visualizar.
- `useDefaultWarehouse` devuelve el parámetro `LGN` si el depósito sigue entre los que el usuario opera; las pantallas operativas lo usan como valor inicial del campo Depósito.

### Pantalla de selección

Componentes en `frontend/src/shared/ui/selection/` (E2.18): `SelectionScreen` y el hook `useSelectionScreen`. La pantalla declara sus campos como `PARAMETERS` (un valor, opcionalmente obligatorio) o `SELECT-OPTIONS` (desde, hasta y selección múltiple); un campo con F4 se indica con `as` (`as: WarehouseField`).

- Cada criterio es la tabla de rangos de SAP: entradas con signo (`I` incluye, `E` excluye), operador (EQ, NE, GT, GE, LT, LE, BT, NB, CP, NP) y límites. Desde E2.12 (D3) CP y NP comparan con un patrón en el que `*` es cualquier texto, sin distinguir mayúsculas (`ILIKE` con el resto escapado); `+` y `#` de SAP no se admiten. Un valor individual con `*` pasa a CP (o NP), como en SAP; en un rango se rechaza (`PATTERN_IN_RANGE`), y en un criterio numérico el backend no acepta CP ni NP. El backend lo aplica con `FilterMode::Selection` de `ListRequest` (`filter[campo][i][sign|option|low|high]`): las entradas I se unen con O y las E se restan; una exclusión no descarta una fila con la columna nula. `selectionFilters` traduce los criterios a esos parámetros; los parámetros simples quedan a cargo de cada pantalla, porque suelen ser parte de la ruta.
- Desde y hasta muestran la primera entrada que incluye un valor o un rango; las demás se ven en el diálogo de selección múltiple, con las cuatro pestañas de SAP. Las de exclusión están deshabilitadas hasta después del MVP. Una fila vacía al final agrega la entrada al escribir en ella. Desde el 25/11/2026 (E2.24) cada valor individual lleva su condición en palabras (igual a, distinto de, mayor que, mayor o igual que, menor que, menor o igual que), los rangos se leen "entre ... y ...", F8 o **Aplicar** toman las entradas, **Borrar pestaña** y **Borrar todo** vacían lo visible o todo, y desde y hasta de la pantalla quedan inhabilitados mientras haya entradas que no muestran. El botón de selección múltiple se rellena y muestra la cantidad de entradas cuando hay más de las que se ven, como la flecha verde de SAP: no se indica solo con color.
- Los límites ofrecen F4 sin tabla de verificación, como en SAP: un rango de A a M no exige que A exista. Los parámetros sí la usan.
- Se ejecuta con F8 (`useTransactionActions({ execute })`): `validate` exige los obligatorios, rechaza valores inexistentes, enteros mal escritos, un rango sin desde y, en enteros, un desde mayor que el hasta; emite un mensaje E y lleva el foco al campo, o al botón de selección múltiple si el error está en una entrada que no se ve. Los límites de texto no se comparan en el navegador: su orden depende de la intercalación de la base.
- El estado vive en el hook: la transacción muestra el resultado, registra Atrás y F3 vuelve a la selección con lo cargado, como en SAP. No va a la URL; compartir una selección es el caso de las variantes.
- La pantalla no es un `<form>`, sino un `<search>`: Enter confirma el campo y no ejecuta, y un `<form>` envolvería los de los popups F4 que se abren adentro.

### Bloques de formulario

Las transacciones se diseñan una por una en JSX, como en el Screen Painter de SAP, y lo común sale de piezas compartidas, como el diccionario de datos que da a cada campo su etiqueta, su F4 y su tabla de verificación (E2.22). `useTransactionForm` (`frontend/src/app/shell/`), sobre TanStack Form y el esquema Zod de la pantalla, devuelve `Form`, `Section` y `Field`:

```tsx
const { Form, Section, Field } = useTransactionForm({ schema, defaultValues, namespace: 'su3', onSave, savedMessage, saveFailedMessage });

<Form>
  <Section name="settings">
    <Field name="locale" options={languageOptions} />
    <Field name="lgn" as={WarehouseField} />
  </Section>
</Form>
```

- `name` está tipado contra el esquema: un nombre inexistente no compila. La etiqueta sale de `fields.<nombre>` y el título del bloque de `sections.<nombre>` en el namespace de la pantalla. Un control especial se pasa con `as`; un control con F4 informa además si el valor existe en la tabla de verificación, y un valor inexistente bloquea la grabación.
- Los componentes se crean una sola vez por formulario: si se recrearan en cada render, React volvería a montar los campos y el foco se perdería al escribir. Cambiar `readOnly` (visualizar) los vuelve a crear, como cambiar de pantalla.
- El error de un campo se ve al salir de él después de editarlo y desde ahí en cada cambio, o al grabar; un campo vacío se marca solo al grabar o ejecutar (E2.24). Desde E2.24 se muestra como ícono en un espacio fijo entre la etiqueta y el control, con el texto en un globo al pasar el puntero o con el foco (`FieldErrorIndicator`), para que un error no corra los campos; el texto sigue en `aria-describedby` y el campo con `aria-invalid` y el primero va como mensaje E a la barra de estado, con la etiqueta y el foco en el campo, como el PAI de SAP.
- Ctrl+S graba con los valores ya transformados por el esquema; al grabar bien informa S y lo grabado pasa a ser el valor sin cambios, lo que apaga el aviso de datos sin grabar. Un 422 de Problem Details carga cada mensaje en su campo (`serverFields` traduce el nombre del backend cuando difiere) y se quita al corregir el valor.
- Enter confirma el campo y no graba. El `<form>` cancela su envío, también el de los formularios de los popups F4 que se abren adentro.
- Los formularios que no son transacciones (login, cambio de contraseña) usan TanStack Form directo con las mismas piezas visuales (`FormFieldLayout`, `FormActions`) y utilidades de errores (`shared/ui/form/`).

### Lo aprendido al construir la grilla, el F4 y la selección

- Un `<dialog>` modal abierto desde un campo no puede fijar su foco inicial con un efecto propio (corre antes del `showModal()` del padre): `GridDialog` recibe `initialFocus`.
- Las teclas del armazón (F3, Mayús+F3, F8, Ctrl+S) se suspenden con un popup abierto; dentro de un popup, F8 la maneja él (copiar en la selección múltiple).
- Los formularios no se anidan: un popup con `<form>` dentro de otro componente con `<form>` enviaría el de afuera con Enter. Las piezas que alojan F4 no usan `<form>`.
- `focusgroup` se usa donde existe, con respaldo propio para los navegadores sin soporte, en lugar del polyfill.

### Códigos de transacción

Se usa el código de SAP donde la transacción tiene equivalente y el rango `ZW` para las propias, con la misma regla que ADR-014 aplica a los movimientos (`Z` es el espacio de nombres de cliente en SAP). La tabla es indicativa: cada etapa confirma sus códigos al implementar la pantalla. En qué se aparta cada transacción de su par de SAP se registra en este ADR o en la planificación, no en la wiki ni en la interfaz, que describen el sistema por sí mismo sin mencionar SAP.

| Etapa | Código | Transacción | Equivalente SAP |
| - | - | - | - |
| E1 | `SU3` | Parámetros de usuario | SU3 |
| E2 | `SU01`; `ZW91`, `ZW92`, `ZW93` | Gestionar usuarios; crear, modificar y visualizar usuario | SU01 (actividades 01, 02 y 03 en una pantalla). Código de usuario propio (`users.username`), hasta 12 caracteres, inmutable; el ingreso sigue con el email (D1 de E2.14). Contraseña inicial con diálogo en modificar (D5) |
| E2 | `SUIM` | Lista de usuarios | SUIM (sistema de información de usuarios), reducido a la lista por usuario, nombre, rol, depósito y estado |
| E2 | `PFCG` | Visualizar rol | PFCG en visualizar: permisos del rol por módulo y usuarios asignados. Los roles los define el código y no se editan (D4 de E2.14) |
| E2 | `ZW01`; `ZW11`, `ZW12`, `ZW13` | Gestionar depósitos; crear, modificar y visualizar depósito | Configuración (sin transacción de usuario) |
| E2 | `ZW02`; `ZW21`, `ZW22`, `ZW23` | Gestionar zonas; crear, modificar y visualizar zona | Tipos de almacén en configuración |
| E2 | `LS01N`, `LS02N`, `LS03N` | Crear, modificar y visualizar ubicación | LS01N, LS02N, LS03N |
| E2 | `LS10` | Crear múltiples ubicaciones | LS10 |
| E2 | `LX03` | Lista de ubicaciones | LX03 (informe de estado de ubicaciones) |
| E2 | `ZW05` | Bloquear o desbloquear ubicación | Indicadores de bloqueo de LS02N; transacción propia por permiso (D2 de E2.12) |
| E2 | `MM01`, `MM02`, `MM03` | Crear, modificar y visualizar catálogo | MM01, MM02, MM03. El depósito opcional de la pantalla inicial suma el stock mínimo, como el nivel de organización centro (D4 de E2.13) |
| E2 | `MM60` | Lista del catálogo | MM60 (lista de materiales) |
| E2 | `XK01`, `XK02`, `XK03` | Crear, modificar y visualizar proveedor | XK01 a XK03 (BP en S/4HANA). `XK01` sin pantalla inicial: el proveedor no tiene un código propio que verificar antes, como la numeración interna (D1 de E2.13) |
| E2 | `ZW06`; `ZW61`, `ZW62`, `ZW63` | Gestionar catálogo del proveedor; crear, modificar y visualizar artículo del proveedor | Registro info (ME11 a ME13) con lista de fuentes obligatoria; transacción propia para no mezclar grabaciones por fila con la ficha del proveedor (D5 de E2.13) |
| E3 | `MMBE` | Lista de stock | MMBE |
| E3 | `MB51` | Lista de movimientos | MB51 |
| E3 | `MB03` | Visualizar operación | MB03 |
| E3 | `LT10` | Transferir stock | LT10 |
| E3 | `MB1C` | Cargar stock inicial | MB1C con movimiento 561 |
| E3 | `MBST` | Revertir operación | MBST |
| E3 | `ZW31` | Ajustar stock | Sin transacción directa (diferencias de almacén 711 y 712) |
| E3 | `ZW32` | Bloquear o desbloquear stock | Sin equivalente directo: SAP pasa el stock a bloqueado con 344 |
| E3 | `ZW33` | Lista de auditoría | SCU3 (registro de cambios) |
| E3 | `ZW34` | Lista de solicitudes de ajuste | Sin equivalente directo: aprobación del ajuste por encima del umbral del artículo |
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
- Campos de solo lectura con el fondo `--color-readonly` y el borde suave, distintos de un vistazo de los editables, como en SAP GUI (E2.24). Las guías de pantalla van en `InfoNotice`, con barra superior de información.
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
- Las pantallas de E2 en adelante usan los componentes de E2.00, E2.17, E2.18 y E2.22 (grilla, campo con F4, pantalla de selección y bloques de formulario) y no tablas, selectores ni formularios propios.
- El depósito es un campo de cada pantalla operativa; las consultas del frontend incluyen el depósito en su clave de caché.
- Los parámetros de usuario, los favoritos, el idioma y mostrar códigos se guardan en el servidor desde E2.21 (DM-24). Lo guardado en el navegador en E1 se sube una sola vez: cada preferencia solo completa lo que el servidor tiene vacío.
- Una contraseña inicial o reseteada reemplaza el armazón por la pantalla de cambio, sin ruta propia, como el diálogo de clave inicial de SAP (REG-021). Un 403 `PASSWORD_CHANGE_REQUIRED` a mitad de la sesión vuelve a esa pantalla.
- Aceptado al cerrar E2.00, E2.17 y E2.18; lo aprendido queda en la sección correspondiente.
- Enmendado el 25/11/2026 al planificar E3: las transacciones de inventario sin gestión propia se agrupan en `ZW31` a `ZW34`. `ZW03`, asignado antes al ajuste, rompía el esquema `ZW0N` para gestión y `ZWN1` a `ZWN3` para sus acciones. Se suman `MB03`, `MB1C`, `MBST`, la lista de auditoría y la lista de solicitudes de ajuste, con títulos Verbo + objeto.
