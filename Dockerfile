
# Etapa 1: Compilar recursos frontend
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY resources/ ./resources/
COPY vite.config.* ./
COPY public/ ./public/

RUN npm run build


# Etapa 2: Aplicación Laravel
FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libxml2-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
        pdo_pgsql \
        mbstring \
        dom \
        simplexml \
        zip \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# Copiar recursos frontend compilados
COPY --from=frontend /app/public/build ./public/build

# Instalar dependencias PHP
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# Configurar Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    && sed -ri \
    -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf \
    && sed -ri \
    -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && chown -R www-data:www-data storage bootstrap/cache

# Puerto de Render
CMD ["bash", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/*:80/*:${PORT:-10000}/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
