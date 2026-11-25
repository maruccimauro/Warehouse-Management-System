# Ayuda de búsqueda (F4)

La **ayuda de búsqueda** ofrece los valores posibles de un campo con la tecla <kbd>F4</kbd>. Un campo que la tiene muestra un botón a la derecha y, al lado del valor, su texto descriptivo (`CEN  Depósito central`).

## Buscar un valor {#buscar}

1. Con el foco en el campo, presione <kbd>F4</kbd> o elija el botón de la ayuda.
2. En **Restricciones**, escriba parte de un valor: la lista de abajo se filtra mientras escribe.
3. Tome el valor con <kbd>Enter</kbd> o doble clic sobre la fila. Si las restricciones dejan un único resultado, <kbd>Enter</kbd> en una restricción lo toma directamente.

<kbd>Esc</kbd> o **Cancelar** cierran la ayuda sin cambiar el valor. Al cerrar, el foco vuelve al campo.

La lista es una [lista](./listas) completa: se ordena y se filtra por columna.

::: tip
Si el campo tiene un valor válido, la ayuda abre con el foco sobre esa fila. Si tiene algo escrito que no existe, abre con ese texto en la primera restricción.
:::

::: info
El botón de la ayuda no recibe el foco con <kbd>Tab</kbd>: con el teclado se usa <kbd>F4</kbd>, y <kbd>Tab</kbd> pasa directamente al campo siguiente.
:::

## Verificación del valor {#verificacion}

El campo verifica lo escrito contra la lista de valores posibles. Convierte los códigos a mayúsculas y, al confirmar con <kbd>Enter</kbd> o al salir del campo, marca un valor inexistente y lo informa con un mensaje E. La pantalla no graba mientras un campo tenga un valor inexistente.

## Campo Depósito {#deposito}

El campo **Depósito** ofrece solo los depósitos asignados al usuario. Las pantallas operativas lo completan con el parámetro de usuario [`LGN`](./parametros-de-usuario#lgn), si ese depósito sigue entre los asignados.
