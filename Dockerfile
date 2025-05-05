FROM php:8.2-fpm

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    git unzip zip curl libicu-dev libonig-dev libxml2-dev \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libpq-dev libxslt1-dev mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl pdo pdo_mysql zip opcache gd \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Créer le dossier de travail
WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Vérifier les extensions PHP requises par Composer
RUN composer check-platform-reqs || { \
    echo "Certaines extensions PHP requises sont manquantes. Vérifiez votre configuration."; exit 1; }

# Configurer Git pour accepter le répertoire comme sûr
RUN git config --global --add safe.directory /var/www/html

# Vérifier les permissions avant d'installer les dépendances PHP
RUN chown -R www-data:www-data /var/www/html

# Installer les dépendances PHP avec des logs détaillés
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --verbose || { \
    echo "Échec de composer install. Vérifiez les dépendances ou les fichiers manquants."; exit 1; } \
    && composer clear-cache # Nettoyer le cache Composer pour réduire la taille de l'image

# Droits pour le cache
RUN chown -R www-data:www-data /var/www/html/var /var/www/html/vendor

# Exposer le port 8000
EXPOSE 8000

# Lancer le serveur Symfony via le serveur PHP natif
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
