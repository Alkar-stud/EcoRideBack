    # Choisissez une image PHP de base (adaptez la version si besoin)
    # Alpine est plus léger, mais peut nécessiter plus de dépendances manuelles
    # Utiliser FPM car Fly.io gère le serveur web devant
    ARG PHP_VERSION=8.2
    FROM php:${PHP_VERSION}-fpm-alpine AS base

    # Variables d'environnement pour Symfony
    ENV APP_ENV=prod \
        APP_DEBUG=0

    WORKDIR /var/www/html

    # Installer les dépendances système nécessaires pour les extensions PHP et Composer
    # git, zip/unzip sont souvent utiles pour Composer
    # icu-dev pour l'extension intl
    # build-base, autoconf, etc. ($PHPIZE_DEPS) pour compiler des extensions PECL
    # libzip-dev pour l'extension zip
    # mongodb dépendances (souvent juste les outils de build et openssl)
    RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        zlib-dev \
        $PHPIZE_DEPS \
        openssl-dev

    # Installer les extensions PHP communes pour Symfony + MongoDB
    RUN docker-php-ext-install -j$(nproc) intl opcache zip pdo pdo_mysql bcmath # Adaptez pdo_mysql si vous ne l'utilisez pas du tout
    # Installer l'extension MongoDB via PECL
    RUN pecl install mongodb && docker-php-ext-enable mongodb

    # Nettoyer les dépendances de build non nécessaires au runtime
    RUN apk del $PHPIZE_DEPS

    # Installer Composer globalement
    COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

    # Copier uniquement les fichiers Composer pour profiter du cache Docker
    COPY composer.json composer.lock symfony.lock* ./
    # Installer les dépendances SANS les dev, optimisé pour la prod
    RUN set -eux; \
        composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

    # Copier le reste de l'application
    COPY . .

    # Générer l'autoloader optimisé et exécuter les scripts Symfony (comme la création du cache)
    RUN set -eux; \
        composer dump-autoload --classmap-authoritative --no-dev; \
        composer run-script post-install-cmd; \
        # Assurez-vous que les répertoires var/cache et var/log sont inscriptibles par le serveur web/fpm
        mkdir -p var/cache var/log; \
        chown -R www-data:www-data var; \
        chmod -R 777 var # (Plus permissif, ajustez si nécessaire pour la sécurité)

    # L'image finale n'a besoin que du code et de PHP-FPM
    # Fly.io gère le serveur web (nginx/caddy) devant php-fpm

    # Le port par défaut de PHP-FPM
    EXPOSE 9000

    # Commande pour démarrer PHP-FPM
    CMD ["php-fpm"]

    
