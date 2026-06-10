# syntax=docker/dockerfile:1

##########################################################################
# base — PHP 8.4 + extensões + Chromium/Node (Spatie Browsershot/laravel-pdf)
##########################################################################
FROM php:8.4-fpm AS base

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

RUN pecl install redis \
    && docker-php-ext-enable redis

# Chromium para Spatie Browsershot / laravel-pdf (geração de PDF)
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y \
    nodejs \
    chromium \
    fonts-liberation \
    libnss3 \
    libnspr4 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libgbm1 \
    libasound2t64 \
    && npm install -g puppeteer \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

##########################################################################
# dev — código via bind mount, php artisan serve na porta 8000
##########################################################################
FROM base AS dev

COPY .docker/entrypoint.dev.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]

##########################################################################
# prod — nginx + php-fpm + supervisor, código embutido na imagem
##########################################################################
FROM base AS prod

RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

COPY .docker/nginx.conf /etc/nginx/sites-available/default
COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY .docker/entrypoint.prod.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# Dependências primeiro para aproveitar cache de camada
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts --no-autoloader

# Código da aplicação
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["docker-entrypoint"]
