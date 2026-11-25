#!/bin/sh
# Arranque de un servidor de desarrollo Node (Vite del frontend, VitePress de la wiki).
# Instala las dependencias si hace falta y ejecuta el comando del servicio (command en
# docker-compose.yml).
set -eu

# Sin package.json (por ejemplo, un checkout parcial) el servicio queda en espera en lugar de reiniciarse en bucle.
if [ ! -f package.json ]; then
    echo "$(pwd): package.json no existe todavía; servicio en espera." >&2
    exec tail -f /dev/null
fi

# node_modules vive en un volumen nombrado: se instala al primer arranque o cuando cambia el lockfile.
if [ ! -f node_modules/.package-lock.json ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
    npm ci
fi

exec "$@"
