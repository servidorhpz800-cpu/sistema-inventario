FROM php:8.1-apache

# Instalar extensiones para la conexión a TiDB Cloud y WhatsApp Cloud API
RUN docker-php-ext-install pdo pdo_mysql curl

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Configurar límites de carga y evitar warnings de PHP en la respuesta web
COPY php-upload.ini /usr/local/etc/php/conf.d/php-upload.ini

# Copiar el proyecto al servidor web
COPY . /var/www/html/

# Asignar permisos de directorio
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
