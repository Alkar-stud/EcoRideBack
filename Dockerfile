FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    zlib1g-dev \
    libzip-dev

RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip

WORKDIR /var/www/html

# Installation de Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copier les fichiers du projet
COPY . .

# Installation des dépendances
RUN composer install --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html

CMD ["php-fpm"]