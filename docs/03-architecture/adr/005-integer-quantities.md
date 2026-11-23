# ADR-005: Cantidades enteras en la unidad base del producto

- Estado: Aceptado
- Fecha: 22/11/2026
- Aceptado: 23/11/2026, en E2.01, al fijar las convenciones de las migraciones

## Contexto

Las cantidades de inventario se suman, restan y comparan constantemente. Los tipos de punto flotante introducen errores de redondeo, y la aritmética decimal en PHP requiere bibliotecas adicionales.

## Decisión

Todas las cantidades se almacenan como `BIGINT` expresadas en la unidad base del producto. Un producto vendido por kilogramo usa gramos como unidad base. La conversión a unidades de presentación (cajas, pallets) se resuelve en una versión posterior con factores de conversión enteros.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| `NUMERIC` con decimales | Correcto en la base, pero obliga a manejar cadenas o una biblioteca decimal en PHP en todo el dominio |
| `FLOAT` o `DOUBLE` | Errores de redondeo inaceptables para inventario |

## Consecuencias

- Aritmética exacta y simple en base de datos y aplicación.
- La interfaz convierte y muestra la unidad correspondiente.
- Precisiones menores a la unidad base no son representables; se resuelve eligiendo bien la unidad base.
- `BIGINT` admite valores hasta 2^63 - 1, pero un `number` de JavaScript es exacto solo hasta 2^53 - 1 (`Number.MAX_SAFE_INTEGER`). La API valida las cantidades como enteros dentro de ese rango y el frontend las valida con `z.number().int().safe()`; el margen de 2^53 unidades base sobra para cualquier depósito.
- Las restricciones `CHECK` de no negatividad y de positividad sobre las columnas de cantidad están en el DDL de `database-design.md`.
- Aceptado el 23/11/2026 en E2.01, junto con la convención de migraciones y la revalidación del DDL en PostgreSQL 18.
