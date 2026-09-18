FROM php:8.2-apache

# 1. Apache necesita "rewrite" para que /api/... llegue a index.php
#    y "headers" para que el navegador revalide HTML, JS y CSS
# 2. PHP necesita pdo_mysql para hablar con MySQL
RUN a2enmod rewrite headers \
    && docker-php-ext-install pdo pdo_mysql \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html
