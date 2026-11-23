#!/bin/sh
# Preparación idempotente del backend antes de arrancar PHP-FPM:
# dependencias, archivo .env y APP_KEY. Cada paso se salta si ya está hecho.
set -eu

cd /var/www/backend

# Los comandos de la aplicación corren como www-data (UID y GID del host) para
# que vendor/, storage/ y .env no queden con dueño root en hosts Linux.
as_app() {
    su-exec www-data "$@"
}

# Dueño del código montado:
# - www-data: Linux con UID y GID del host bien configurados; no hay nada que hacer.
# - root: Docker Desktop (Windows, macOS), que presenta el montaje como root y
#   persiste chown como metadato sin afectar al host. Se asigna una sola vez.
# - otro UID: Linux con UID o GID distintos en .env. Se corta para no cambiar el
#   dueño de los archivos del host.
owner_uid=$(stat -c %u .)
app_uid=$(id -u www-data)
if [ "$owner_uid" = "0" ] && [ "$app_uid" != "0" ]; then
    echo "app: asignando backend/ a www-data (montaje de Docker Desktop)." >&2
    chown -R www-data:www-data .
elif [ "$owner_uid" != "$app_uid" ]; then
    echo "app: backend/ pertenece al UID $owner_uid y www-data tiene UID $app_uid." >&2
    echo "app: definir UID y GID en .env con los valores de 'id -u' e 'id -g' y reconstruir: docker compose build app" >&2
    exit 1
fi

if [ ! -f composer.json ]; then
    echo "app: backend/composer.json no existe; se arranca sin preparar la aplicación." >&2
    exec "$@"
fi

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/composer/installed.json ]; then
    as_app composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    as_app cp .env.example .env
fi

if ! grep -Eq '^APP_KEY=.+' .env; then
    as_app php artisan key:generate --no-interaction
fi

exec "$@"
