#!/bin/sh
# Arranque del servidor de desarrollo de Vite.
set -eu

# Sin package.json (por ejemplo, un checkout parcial) el servicio queda en espera en lugar de reiniciarse en bucle.
if [ ! -f package.json ]; then
    echo "frontend: package.json no existe todavía; servicio en espera." >&2
    exec tail -f /dev/null
fi

# node_modules vive en un volumen nombrado: se instala al primer arranque o cuando cambia el lockfile.
if [ ! -f node_modules/.package-lock.json ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
    npm ci
fi

exec npm run dev -- --host 0.0.0.0 --port 5173
