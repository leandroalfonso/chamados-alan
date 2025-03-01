FROM php:8.2-apache

# Instalar extensões necessárias do PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Configurar o PHP
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/docker-php-ext-error-reporting.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-error-reporting.ini

# Copiar configuração do Apache
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Configurar o DocumentRoot do Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
