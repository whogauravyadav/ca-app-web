# syntax=docker/dockerfile:1

# --- Build React admin ---
FROM node:20-alpine AS admin-build
WORKDIR /admin
COPY admin/package.json admin/package-lock.json ./
RUN npm ci
COPY admin/ ./
ENV VITE_BASE=/
RUN npm run build

# --- PHP / Apache app ---
FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install -j "$(nproc)" \
        zip \
        pdo_mysql \
        mysqli \
        opcache \
        bcmath \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers setenvif

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && rm -rf admin/node_modules admin/src admin/public admin/index.html admin/vite.config.js admin/eslint.config.js || true

# Ship React admin at site root (keeps Laravel public/index.php for /api + /up)
COPY --from=admin-build /admin/dist/ /var/www/html/public/

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chmod -R a+rX /var/www/html \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
