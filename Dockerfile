# ============================================
# Dockerfile - Vite & Gourmand (Symfony 6/7)
# ============================================
FROM php:8.2-fpm

# Arguments pour l'utilisateur
ARG USER_ID=1000
ARG GROUP_ID=1000

# Variables d'environnement
ENV COMPOSER_ALLOW_SUPERUSER=1

# Installation des dépendances système (avec libicu-dev pour intl)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    libssl-dev \
    pkg-config \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installation de l'extension MongoDB
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration PHP pour la production
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Configuration PHP personnalisée
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Répertoire de travail
WORKDIR /var/www/html

# Exposer le port PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
