# Utilise l'image officielle PHP avec Apache
FROM php:8.2-apache

# Installe les extensions nécessaires pour Symfony et nettoie le cache
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl zip pdo pdo_mysql gd \
    && a2enmod rewrite ssl headers \
    && pecl install mongodb && docker-php-ext-enable mongodb \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod ssl
RUN mkdir -p /etc/apache2/ssl
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/apache.key \
    -out /etc/apache2/ssl/apache.crt \
    -subj "/C=FR/ST=Paris/L=Paris/O=EcoRide/CN=localhost"

# Ajout configuration SSL
RUN echo '<VirtualHost *:443>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html/public\n\
    SSLEngine on\n\
    SSLCertificateFile /etc/apache2/ssl/apache.crt\n\
    SSLCertificateKeyFile /etc/apache2/ssl/apache.key\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/default-ssl.conf \
    && a2ensite default-ssl

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définit le répertoire de travail
WORKDIR /var/www/html

# Copie les fichiers de l'application mais ignore les fichiers inutiles
COPY . .

# Configure Apache pour utiliser le répertoire public comme racine
#RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf && \
#    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
#    mkdir -p var/cache var/log


# Donne les permissions finales
RUN chown -R www-data:www-data /var/www/html

USER www-data

# Installe les dépendances Symfony
RUN composer install --no-scripts --optimize-autoloader && \
    composer dump-env prod
#RUN composer require symfony/apache-pack
RUN composer config extra.symfony.allow-contrib true --no-interaction && \
    composer require symfony/apache-pack --no-interaction

USER root


# Expose le port 8000
EXPOSE 8000

# Commande par défaut
CMD ["apache2-foreground"]
