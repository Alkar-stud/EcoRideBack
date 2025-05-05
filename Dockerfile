FROM php:8.1-fpm

# Installer les extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql intl opcache

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copier les fichiers de l'application
WORKDIR /var/www/html
COPY . .

# Définir les permissions
RUN chown -R www-data:www-data /var/www/html

# Commande par défaut
CMD ["php", "-S", "0.0.0.0:80", "-t", "public"]
