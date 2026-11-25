# Formularios

Las transacciones que crean o modifican datos presentan sus campos en bloques con título, con la etiqueta a la izquierda y el valor a la derecha. En pantallas angostas la etiqueta queda arriba del valor.

## Completar y grabar {#grabar}

- Los campos obligatorios están marcados en la etiqueta.
- <kbd>Enter</kbd> confirma el campo y no graba: los datos se graban con **Grabar** o <kbd>Ctrl</kbd>+<kbd>S</kbd>.
- Al grabar bien, la barra de estado muestra un mensaje S y la pantalla queda sin cambios pendientes.

## Errores en los campos {#errores}

Un campo con un valor no válido muestra el error debajo, con el campo marcado. El error aparece al salir del campo después de editarlo y desde ahí se actualiza con cada cambio; también aparece al grabar.

Al grabar con errores, el sistema no graba, lleva el foco al primer campo con error y lo informa en la barra de estado con un mensaje E que nombra el campo, por ejemplo **LGN: El valor no existe.** Lo mismo ocurre cuando el servidor rechaza un dato ([`VALIDATION_FAILED`](/referencia/mensajes#validation-failed)): cada mensaje aparece en su campo y se quita al corregir el valor.

## Visualizar {#visualizar}

Algunas transacciones muestran los datos en modo de visualización: los campos son de solo lectura, **Grabar** queda deshabilitado y salir no pide confirmación.
