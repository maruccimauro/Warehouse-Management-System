# ADR-011: Incorporación diferida de Redis

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-003

## Contexto

El stack sugerido incluye Redis. Agregarlo desde el inicio suma un servicio a operar sin una necesidad concreta.

## Decisión

Hasta la etapa de reportes las colas usan el driver `database`. Redis se incorpora cuando aparecen necesidades medibles: colas de exportación, caché de reportes y tareas programadas. Redis nunca almacena estado de inventario ni coordina bloqueos de stock; la fuente de verdad es PostgreSQL.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Redis desde el primer día | Complejidad operativa sin beneficio inicial |
| Redis como caché de existencias | Riesgo de mostrar stock desactualizado en operaciones críticas |

## Consecuencias

- Menos servicios durante el desarrollo del núcleo.
- La migración del driver de colas es un cambio de configuración.
