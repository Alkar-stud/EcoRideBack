FROM php:8.2-cli

# Installer les dépendances système nécessaires à GD et MongoDB
RUN apt-get update && apt-get install -y \
    git unzip zip curl libicu-dev libonig-dev libxml2-dev \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libpq-dev mariadb-client libjpeg-dev libpng-dev libwebp-dev libxpm-dev \
    libssl-dev pkg-config gnupg2

# Installer les extensions GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd

# Installer les extensions PDO MySQL, intl, zip, etc.
RUN docker-php-ext-install intl pdo pdo_mysql zip

# Installer l’extension MongoDB via PECL
RUN pecl install mongodb \
    && echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Créer le dossier de travail
WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Installer les dépendances PHP via Composer
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Droits Symfony
RUN chown -R www-data:www-data /var/www/html/var /var/www/html/vendor

# Exposer le port 8000
EXPOSE 8000

# Lancer le serveur PHP
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
