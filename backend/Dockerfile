# Cuentia backend — production image (Apache + PHP)
# ES: Imagen de producción del backend (Apache + PHP)

FROM php:8.4-apache

# System libs + PHP extensions Symfony/Doctrine need.
# ES: Librerías del sistema + extensiones PHP que necesitan Symfony/Doctrine.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql intl zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Serve Symfony's public/ directory.
# ES: Servir el directorio public/ de Symfony.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Install PHP dependencies (production).
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

# App code.
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && chown -R www-data:www-data var

# On boot: make Apache listen on the platform's $PORT (Render/Koyeb/Fly/Railway all
# inject it; default 8080), run DB migrations, then start Apache in the foreground.
#
# A failing migration must abort the boot. Serving traffic against a schema that does not match the
# entities is how a login turns into a 500: the platform keeps the previous release live when the new
# one fails its health check, which is exactly what we want.
#
# ES: Al arrancar: que Apache escuche en el $PORT de la plataforma (lo inyectan Render/Koyeb/Fly/Railway;
# por defecto 8080), ejecuta las migraciones e inicia Apache en primer plano.
#
# Una migración fallida debe abortar el arranque. Servir tráfico con un esquema que no cuadra con las
# entidades es justo como un login acaba en 500: la plataforma mantiene viva la versión anterior cuando
# la nueva no pasa el health check, que es lo que queremos.
CMD ["sh", "-c", "\
    set -e; \
    PORT=${PORT:-8080}; \
    sed -ri \"s!Listen 80!Listen $PORT!\" /etc/apache2/ports.conf; \
    sed -ri \"s!:80>!:$PORT>!\" /etc/apache2/sites-available/000-default.conf; \
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; \
    apache2-foreground"]
