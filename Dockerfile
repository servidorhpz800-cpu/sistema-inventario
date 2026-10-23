FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

FROM php:8.1-apache

# Instalar extensión PDO MySQL para la conexión a TiDB Cloud
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql simplexml curl fileinfo \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Configurar límites de carga y evitar warnings de PHP en la respuesta web
COPY php-upload.ini /usr/local/etc/php/conf.d/php-upload.ini

# Copiar el proyecto al servidor web
COPY . /var/www/html/
COPY --from=dependencies /app/vendor /var/www/html/vendor

# Asignar permisos de directorio
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
