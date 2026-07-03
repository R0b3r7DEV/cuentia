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

# Railway (and most PaaS) inject $PORT — make Apache listen on it.
# ES: Railway (y la mayoría de PaaS) inyectan $PORT — que Apache escuche en él.
RUN sed -ri -e 's!Listen 80!Listen ${PORT}!' /etc/apache2/ports.conf \
    && sed -ri -e 's!:80>!:${PORT}>!' /etc/apache2/sites-available/000-default.conf

# On boot: run DB migrations, then start Apache in the foreground.
# ES: Al arrancar: ejecuta las migraciones y luego inicia Apache en primer plano.
CMD ["sh", "-c", "php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true; apache2-foreground"]
