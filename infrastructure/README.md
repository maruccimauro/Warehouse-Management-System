# Infraestructura

| Directorio | Contenido |
| - | - |
| `docker/php/` | Imagen de la aplicación: PHP-FPM con `pdo_pgsql`, `intl`, `bcmath`, `zip`, `pcntl` y Composer; zona horaria UTC |
| `docker/nginx/` | Configuración de Nginx: un solo host para la SPA y la API |
| `docker/node/` | Arranque del servidor de desarrollo de Vite |
| `scripts/` | Scripts de soporte del entorno: inicialización, generación de claves, tareas de mantenimiento |

## Servicios de desarrollo

El archivo `docker-compose.yml` de la raíz orquesta los servicios y toma sus variables de `.env` (ver `.env.example`).

| Servicio | Imagen | Función |
| - | - | - |
| `app` | `php:8.5-fpm-alpine` (construida) | Laravel sobre PHP-FPM. Arranca cuando `db` está sano |
| `web` | `nginx:1.30-alpine` | Punto de entrada en `WEB_PORT`. `/api` y `/sanctum` van a `app`; el resto, a `frontend` |
| `db` | `postgres:18-alpine` | Base de datos con volumen `db_data` y healthcheck con `pg_isready` |
| `frontend` | `node:26-alpine` | Servidor de desarrollo de Vite, con `node_modules` en el volumen `frontend_node_modules` |

Servir la SPA y la API desde el mismo host hace que la cookie de sesión de Sanctum sea de primera parte en todos los entornos y evita configurar CORS y `SameSite` entre orígenes (ADR-009).
