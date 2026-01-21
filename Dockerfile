FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    sqlite-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_sqlite gd zip bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

# Copy package files for npm
COPY package.json package-lock.json ./
RUN npm ci

# Copy application code
COPY . .

# Finish composer install
RUN composer dump-autoload --optimize

# Build frontend
RUN npm run build

# Create storage link and set permissions
RUN mkdir -p storage/app/public \
    && php artisan storage:link || true \
    && chmod -R 775 storage bootstrap/cache

# Expose port
EXPOSE 8080

# Start command - use PHP built-in server from public directory
CMD php artisan migrate --force && php -S 0.0.0.0:8080 -t public public/index.php
