# Formularios

Las transacciones que crean o modifican datos presentan sus campos en bloques con título, con la etiqueta a la izquierda y el valor a la derecha. En pantallas angostas la etiqueta queda arriba del valor.

## Completar y grabar {#grabar}

- Los campos obligatorios están marcados en la etiqueta.
- <kbd>Enter</kbd> confirma el campo y no graba: los datos se graban con **Grabar** o <kbd>Ctrl</kbd>+<kbd>S</kbd>.
- Al grabar bien, la barra de estado muestra un mensaje S y la pantalla queda sin cambios pendientes.

## Errores en los campos {#errores}

Un campo con un valor no válido queda marcado y muestra un ícono de error entre la etiqueta y el valor. El texto del error aparece en un globo amarillo al pasar el puntero por el ícono o mientras el campo tiene el foco.

El error de formato aparece al salir del campo después de editarlo y desde ahí se actualiza con cada cambio. Un campo obligatorio vacío no se marca al recorrer la pantalla: se marca al grabar o al ejecutar.

Al grabar con errores, el sistema no graba, lleva el foco al primer campo con error y lo informa en la barra de estado con un mensaje E que nombra el campo, por ejemplo **LGN: El valor no existe.** Lo mismo ocurre cuando el servidor rechaza un dato ([`VALIDATION_FAILED`](/referencia/mensajes#validation-failed)): cada mensaje aparece en su campo y se quita al corregir el valor.

## Visualizar {#visualizar}

Algunas transacciones muestran los datos en modo de visualización: los campos son de solo lectura, **Grabar** queda deshabilitado y salir no pide confirmación.

Un campo de solo lectura tiene un fondo gris claro y un borde más suave que un campo editable. Así se distinguen de un vistazo, por ejemplo, el código de un registro ya grabado, que no se puede cambiar, y los campos de una ficha en visualización.

## Avisos informativos {#avisos}

Las guías de una pantalla (qué hace la transacción, qué pasa al grabar o cuántos registros se van a crear) aparecen en un recuadro con una barra superior **Información** y el ícono de información. No son mensajes de error: los resultados de una acción se informan en la barra de estado.
