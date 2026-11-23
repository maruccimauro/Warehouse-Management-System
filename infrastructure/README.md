# Infraestructura

| Directorio | Contenido |
| - | - |
| `docker/` | Dockerfiles y configuración de cada servicio del entorno (PHP-FPM, Nginx) |
| `scripts/` | Scripts de soporte del entorno: inicialización, generación de claves, tareas de mantenimiento |

El archivo `docker-compose.yml` de la raíz orquesta los servicios y toma sus variables de `.env` (ver `.env.example`).
