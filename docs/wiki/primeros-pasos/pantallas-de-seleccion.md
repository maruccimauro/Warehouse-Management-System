# Pantallas de selección

Las consultas y los informes empiezan con una **pantalla de selección**: se indican los criterios y se ejecuta con <kbd>F8</kbd>, como en SAP. El resultado aparece en la misma transacción y <kbd>F3</kbd> vuelve a la selección con los criterios cargados.

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

Los límites ofrecen la [ayuda de búsqueda](./ayuda-de-busqueda) con <kbd>F4</kbd>, pero no exigen que el valor exista: un rango de A a M no necesita un código A. En cambio, el valor de un parámetro sí se verifica.

## Selección múltiple {#seleccion-multiple}

El botón al lado de **hasta** abre **Selección múltiple**, que admite varios valores y rangos para el mismo criterio. Se unen con O: una fila entra si cumple cualquiera de ellos.

1. En la pestaña **Valores individuales**, escriba cada valor en la fila vacía del final: al escribir se agrega otra.
2. En la pestaña **Rangos**, complete **Desde** y **Hasta** de cada rango.
3. Elija **Copiar (F8)** o presione <kbd>F8</kbd>.

Cuando el criterio tiene más entradas de las que se ven en desde y hasta, el botón aparece relleno e indica la cantidad (**3 entradas**).

::: info
Las pestañas **Excluir valores individuales** y **Excluir rangos** todavía no están disponibles.
:::

## Ejecutar {#ejecutar}

<kbd>F8</kbd> o **Ejecutar** verifican la selección antes de consultar. Si falta un obligatorio, un valor no existe, un número no es entero o un rango numérico tiene el desde mayor que el hasta, la barra de estado muestra un mensaje E y el foco va al campo con el problema.

<kbd>Enter</kbd> confirma el campo y no ejecuta.
