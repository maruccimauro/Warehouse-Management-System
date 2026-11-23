# Imagen de la aplicación Laravel (PHP-FPM). Versión fijada en la menor: ver decisiones de E1.
FROM php:8.5-fpm-alpine

# UID y GID del usuario del host, para que los archivos generados dentro del
# contenedor (vendor, caché, logs) no queden con dueño root en hosts Linux.
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev \
    && apk add --no-cache icu-libs libzip libpq git unzip shadow su-exec \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_pgsql zip \
    && apk del .build-deps \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Caché de Composer escribible por www-data.
ENV COMPOSER_HOME=/tmp/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/backend
