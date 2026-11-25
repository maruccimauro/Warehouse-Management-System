# Pantallas de selección

Las consultas y los informes empiezan con una **pantalla de selección**: se indican los criterios y se ejecuta con <kbd>F8</kbd>. El resultado aparece en la misma transacción y <kbd>F3</kbd> vuelve a la selección con los criterios cargados.

Hay dos clases de campos:

- **Parámetros**: un solo valor, por ejemplo el depósito. Un parámetro obligatorio está marcado en la etiqueta.
- **Criterios**: un valor, un rango o varios. Muestran dos campos, **desde** y **hasta**, y un botón de selección múltiple.

## Criterios {#criterios}

| Lo que se completa | Lo que selecciona |
| - | - |
| Nada | Todos los valores |
| Solo **desde** | Ese valor |
| **Desde** y **hasta** | El rango, incluidos los dos límites |
| Solo **hasta** | Error: un rango necesita el valor desde |

## Patrones {#patrones}

En un criterio de texto, un valor con `*` es un **patrón**: el `*` reemplaza cualquier cantidad de caracteres. `A-01*` selecciona todo lo que empieza con `A-01`; `*-04`, lo que termina en `-04`. El patrón no distingue mayúsculas de minúsculas.

El `*` solo se admite en valores individuales, en el campo **desde** o en la pestaña **Valores individuales** de la selección múltiple. En un rango se rechaza con [`PATTERN_IN_RANGE`](/referencia/mensajes#pattern-in-range).

## Ayuda de búsqueda en los criterios {#ayuda-en-criterios}

Los límites ofrecen la [ayuda de búsqueda](./ayuda-de-busqueda) con <kbd>F4</kbd>, pero no exigen que el valor exista: un rango de A a M no necesita un código A. En cambio, el valor de un parámetro sí se verifica.

## Selección múltiple {#seleccion-multiple}

El botón al lado de **hasta** abre **Selección múltiple**, que admite varios valores y rangos para el mismo criterio. Se unen con O: una fila entra si cumple cualquiera de ellos.

1. En la pestaña **Valores individuales**, elija la **Condición** de cada fila (**igual a**, **distinto de**, **mayor que**, **mayor o igual que**, **menor que** o **menor o igual que**) y escriba el valor en la fila vacía del final: al escribir se agrega otra.
2. En la pestaña **Rangos**, complete **Desde** y **Hasta** de cada rango: la fila se lee "entre … y …".
3. Elija **Aplicar** o presione <kbd>F8</kbd>.

El botón de la papelera, al final de cada fila, borra esa fila; la fila bajo el puntero se resalta. **Borrar pestaña** vacía la pestaña visible y **Borrar todo** vacía todas.

Cuando el criterio tiene más entradas de las que se ven en desde y hasta, o una condición distinta de **igual a**, el botón aparece relleno e indica la cantidad (**3 entradas**). Mientras tanto, desde y hasta quedan bloqueados: la selección se modifica en **Selección múltiple**, y vuelven a habilitarse cuando queda una sola entrada simple.

::: info
Las pestañas **Excluir valores individuales** y **Excluir rangos** todavía no están disponibles.
:::

## Ejecutar {#ejecutar}

<kbd>F8</kbd> o **Ejecutar** verifican la selección antes de consultar. Si hay un problema, la barra de estado muestra un mensaje E con su código y el foco va al campo:

| Problema | Código |
| - | - |
| Falta un obligatorio | [`REQUIRED_FIELDS_MISSING`](/referencia/mensajes#required-fields-missing) |
| El valor de un parámetro no existe | [`VALUE_NOT_FOUND`](/referencia/mensajes#value-not-found) |
| Un número no es entero | [`INTEGER_EXPECTED`](/referencia/mensajes#integer-expected) |
| Un rango tiene hasta sin desde | [`RANGE_LOW_MISSING`](/referencia/mensajes#range-low-missing) |
| Un rango numérico tiene el desde mayor que el hasta | [`RANGE_INVERTED`](/referencia/mensajes#range-inverted) |
| Un rango tiene el comodín `*` | [`PATTERN_IN_RANGE`](/referencia/mensajes#pattern-in-range) |

<kbd>Enter</kbd> confirma el campo y no ejecuta.
