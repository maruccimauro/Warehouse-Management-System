# Warehouse Management System

Sistema de gestión de depósitos centrado en la consistencia transaccional del inventario: sin stock negativo, sin operaciones duplicadas y sin condiciones de carrera entre operadores. Interfaz inspirada en SAP GUI (ADR-016).

## Documentos maestros

| Documento | Contenido |
|---|---|
| `docs/03-architecture/adr/` | Decisiones de arquitectura. Son la base del diseño; si una propuesta mejor choca con un ADR, se plantea el cambio. |
| `docs/02-analysis/business-rules.md`, `use-cases.md` | Reglas de negocio y casos de uso. |
| `docs/03-architecture/domain-model.md`, `database-design.md` | Modelo de dominio y diseño de la base. |
| `docs/01-business/glossary.md` | Vocabulario del dominio. |
| `docs/wiki/` | Wiki de usuario (VitePress), se mantiene con la skill `generar-wiki-wms`. |

## Stack y comandos

Laravel (PHP), PostgreSQL, React con TypeScript, Docker Compose. Los comandos de arranque y verificación están en `README.md`. En Git Bash, anteponer `MSYS_NO_PATHCONV=1` a los `docker compose exec` que pasan rutas del contenedor.

## Reglas del proyecto

Las reglas de `.claude/rules/` (nomenclatura, responsabilidades por transacción, wiki) y la skill de `.claude/skills/` se aplican siempre.

## Canal privado: `.local/`

Además de lo versionado (este archivo y `.claude/`), el proyecto tiene un segundo canal de instrucciones en `.local/`, que está en `.gitignore` y no se publica. Replica la misma estructura (`.local/.claude/CLAUDE.md`, `.local/.claude/rules/`, `.local/.claude/skills/`) y guarda la forma de trabajo del autor, la planificación por etapas, handoffs, guías de prueba y todo lo que no corresponde mostrar en el repositorio.

Al iniciar cada sesión, leer `.local/.claude/CLAUDE.md` y todo lo que referencia, y tomarlo como complemento de este archivo con la misma autoridad. Antes de una tarea, revisar tanto `.claude/rules/` y `.claude/skills/` como `.local/.claude/rules/` y `.local/.claude/skills/`. Si `.local/` no existe (por ejemplo, en un clon público), pasar por alto este apartado.

@.local/.claude/CLAUDE.md
