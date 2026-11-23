#!/bin/sh
# Crea la base de pruebas junto a la de desarrollo. Las pruebas corren contra
# PostgreSQL real, nunca SQLite: restricciones y triggers son parte del sistema
# bajo prueba. La imagen ejecuta este script solo al inicializar un volumen vacío.
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
CREATE DATABASE wms_testing;
SQL
