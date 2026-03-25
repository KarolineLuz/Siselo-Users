FROM php:8.1-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/>|<Directory /var/www/html/public>|g' /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80