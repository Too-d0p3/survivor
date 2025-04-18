FROM php:8.4-fpm

# Install required system packages
RUN apt-get update && apt-get install -y \
    unzip curl git zip libpq-dev libzip-dev libonig-dev libxml2-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql zip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer
