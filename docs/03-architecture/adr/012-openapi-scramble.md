# ADR-012: Documentación OpenAPI generada con Scramble

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-010

## Contexto

El proyecto requiere documentación OpenAPI de la API REST. La documentación mantenida a mano tiende a quedar desactualizada respecto del código.

## Decisión

Se usa `dedoc/scramble`, que genera la especificación a partir de las rutas, los Form Requests y los API Resources sin anotaciones. Se agregan extensiones para documentar las respuestas Problem Details con sus códigos de dominio y la cabecera `Idempotency-Key`.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Scribe (`knuckleswtf/scribe`) | Requiere regenerar y mantener comentarios; aporta ejemplos de respuesta, pero con más mantenimiento |
| L5 Swagger con anotaciones | Gran volumen de anotaciones repetidas y desincronización frecuente con el código |

## Consecuencias

- Documentación alineada con las validaciones reales.
- Lo que Scramble no infiere se documenta con sus mecanismos de extensión.
