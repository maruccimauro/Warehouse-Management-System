# Infraestructura

| Directorio | Contenido |
| - | - |
| `docker/php/` | Imagen de la aplicación: PHP-FPM con `pdo_pgsql`, `intl`, `bcmath`, `zip`, `pcntl` y Composer; zona horaria UTC. `entrypoint.sh` instala dependencias, crea `backend/.env` y genera `APP_KEY` al primer arranque |
| `docker/nginx/` | Configuración de Nginx: un solo host para la SPA, la API y la wiki |
| `docker/postgres/initdb/` | Scripts de inicialización de un volumen vacío: crea la base de pruebas `wms_testing` |
| `docker/node/` | Arranque de los servidores de desarrollo Node (Vite del frontend y VitePress de la wiki): instala dependencias si cambió el lockfile y ejecuta el `command` del servicio |
| `scripts/` | Scripts de soporte del entorno: inicialización, generación de claves, tareas de mantenimiento |

## Servicios de desarrollo

El archivo `docker-compose.yml` de la raíz orquesta los servicios y toma sus variables de `.env` (ver `.env.example`).

| Servicio | Imagen | Función |
| - | - | - |
| `app` | `php:8.5-fpm-alpine` (construida) | Laravel sobre PHP-FPM. Arranca cuando `db` está sano |
| `scheduler` | La imagen de `app` | Tareas programadas de Laravel con `php artisan schedule:work`: limpieza diaria de las claves de idempotencia |
| `web` | `nginx:1.30-alpine` | Punto de entrada en `WEB_PORT`. `/api` y `/sanctum` van a `app`; `/docs/`, a `wiki`; el resto, a `frontend` |
| `db` | `postgres:18-alpine` | Bases `wms` y `wms_testing`, zona horaria UTC, volumen `db_data` y healthcheck con `pg_isready` |
| `frontend` | `node:26-alpine` | Servidor de desarrollo de Vite, con `node_modules` en el volumen `frontend_node_modules` |
| `wiki` | `node:26-alpine` | Wiki de usuario (`docs/wiki/`) con `vitepress dev` en el puerto 5174, servida en `http://localhost:8080/docs/`; `node_modules` en el volumen `wiki_node_modules` |

Servir la SPA y la API desde el mismo host hace que la cookie de sesión de Sanctum sea de primera parte en todos los entornos y evita configurar CORS y `SameSite` entre orígenes (ADR-009).
