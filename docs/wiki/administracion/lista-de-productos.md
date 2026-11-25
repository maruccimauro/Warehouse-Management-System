# Lista de productos (MM60)

La transacción `MM60` lista los productos del catálogo con su categoría, unidad base y estado. Cualquier usuario con sesión la puede abrir y está en el menú en **Logística ▸ Catálogo**.

## Selección {#seleccion}

La transacción empieza con una [pantalla de selección](/primeros-pasos/pantallas-de-seleccion). Todos los criterios son opcionales: sin criterios, <kbd>F8</kbd> lista todo el catálogo.

### SKU {#sku}

Restringe los SKU con un valor, un rango, varios valores o un patrón con `*`:

| Entrada | Resultado |
| - | - |
| `TOR-M6-30` | Ese producto |
| `TOR-M6-30` a `TOR-M8-40` | Los SKU del rango, en orden alfabético |
| `TOR*` | Los que empiezan con `TOR` |
| `*M8*` | Los que contienen `M8` |

Ver [patrones](/primeros-pasos/pantallas-de-seleccion#patrones).

### Nombre {#name}

Los productos cuyo nombre contiene el texto, sin distinguir mayúsculas.

### Código de barras {#barcode}

El producto con ese código de barras, escrito completo.

### Categoría {#category}

Los productos de esa categoría, elegida con <kbd>F4</kbd>. No incluye los de sus subcategorías.

## Resultado {#resultado}

La [lista](/primeros-pasos/listas) muestra SKU, nombre, código de barras, categoría, unidad base, estado y peso.

- Se ordena por SKU o por nombre y se filtra por estado.
- Doble clic o <kbd>Enter</kbd> sobre una fila abre el producto en [Visualizar producto](./visualizar-producto).
- <kbd>F3</kbd> vuelve a la selección con los criterios cargados.
