# Lista de ubicaciones (LX03)

La transacción `LX03` lista las ubicaciones de un depósito con su zona, coordenadas, estado y capacidad. Cualquier usuario con sesión la puede abrir, para los depósitos que puede consultar, y está en el menú en **Logística ▸ Estructura del depósito**.

## Selección {#seleccion}

La transacción empieza con una [pantalla de selección](/primeros-pasos/pantallas-de-seleccion). Complete los criterios y ejecute con <kbd>F8</kbd>.

### Depósito {#warehouse}

Obligatorio. Depósito cuyas ubicaciones se listan, entre los que el usuario puede consultar. Se completa con el parámetro [`LGN`](/primeros-pasos/parametros-de-usuario#lgn).

### Ubicación {#code}

Opcional. Restringe los códigos con un valor, un rango, varios valores o un patrón con `*`:

| Entrada | Resultado |
| - | - |
| `A-01-01` | Esa ubicación |
| `A-01-01` a `A-01-99` | Las ubicaciones del rango |
| `A-01*` | Las que empiezan con `A-01` |
| `*-04` | Las que terminan en `-04` |

Ver [patrones](/primeros-pasos/pantallas-de-seleccion#patrones).

## Resultado {#resultado}

A la izquierda, el árbol de zonas del depósito; a la derecha, la [lista](/primeros-pasos/listas) de ubicaciones.

- **Todas las zonas** muestra todas las ubicaciones de la selección. Elegir una zona en el árbol, con clic o con <kbd>Enter</kbd>, deja solo las de esa zona. En el árbol, las flechas arriba y abajo mueven el foco.
- La lista se ordena por ubicación, pasillo, estantería, nivel o estado, y se filtra por estado y por si la ubicación es de sistema.
- Doble clic o <kbd>Enter</kbd> sobre una fila abre la ubicación en [Visualizar ubicación](./visualizar-ubicacion).
- <kbd>F3</kbd> vuelve a la selección con los criterios cargados.
