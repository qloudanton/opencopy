# Force specific PHP version to bust Railway cache - symfony/filesystem 8.0.1 requires PHP 8.4+
FROM php:8.4.3-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    unzip \
    nodejs \
    npm \
    supervisor

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN composer dump-autoload --optimize
RUN npm run build

# Laravel production setup
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Create startup script to run migrations and cache at runtime
RUN echo '#!/bin/bash' > /app/start.sh && \
    echo 'set -e' >> /app/start.sh && \
    echo '' >> /app/start.sh && \
    echo '# Wait a moment for any DB to be ready' >> /app/start.sh && \
    echo 'sleep 2' >> /app/start.sh && \
    echo '' >> /app/start.sh && \
    echo '# Create SQLite database if using SQLite and file does not exist' >> /app/start.sh && \
    echo 'if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then' >> /app/start.sh && \
    echo '  DB_PATH="${DB_DATABASE:-/app/database/database.sqlite}"' >> /app/start.sh && \
    echo '  if [ ! -f "$DB_PATH" ]; then' >> /app/start.sh && \
    echo '    echo "Creating SQLite database at $DB_PATH"' >> /app/start.sh && \
    echo '    mkdir -p "$(dirname "$DB_PATH")"' >> /app/start.sh && \
    echo '    touch "$DB_PATH"' >> /app/start.sh && \
    echo '  fi' >> /app/start.sh && \
    echo 'fi' >> /app/start.sh && \
    echo '' >> /app/start.sh && \
    echo '# Run migrations' >> /app/start.sh && \
    echo 'php artisan migrate --force --no-interaction' >> /app/start.sh && \
    echo '' >> /app/start.sh && \
    echo '# Create storage link' >> /app/start.sh && \
    echo 'php artisan storage:link || true' >> /app/start.sh && \
    echo '' >> /app/start.sh && \
    echo '# Cache configuration for production performance' >> /app/start.sh && \
    echo 'php artisan config:cache' >> /app/start.sh && \
    echo 'php artisan route:cache' >> /app/start.sh && \
    echo 'php artisan view:cache' >> /app/start.sh && \
    echo '' >> /app/start.sh && \
    echo '# Start supervisor' >> /app/start.sh && \
    echo 'exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf' >> /app/start.sh && \
    chmod +x /app/start.sh

RUN mkdir -p /etc/supervisor/conf.d
RUN echo '[supervisord]' > /etc/supervisor/conf.d/app.conf && \
    echo 'nodaemon=true' >> /etc/supervisor/conf.d/app.conf && \
    echo 'logfile=/dev/null' >> /etc/supervisor/conf.d/app.conf && \
    echo 'logfile_maxbytes=0' >> /etc/supervisor/conf.d/app.conf && \
    echo '' >> /etc/supervisor/conf.d/app.conf && \
    echo '[program:web]' >> /etc/supervisor/conf.d/app.conf && \
    echo 'command=php artisan serve --host=0.0.0.0 --port=8080' >> /etc/supervisor/conf.d/app.conf && \
    echo 'autostart=true' >> /etc/supervisor/conf.d/app.conf && \
    echo 'autorestart=true' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stdout_logfile=/dev/stdout' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stdout_logfile_maxbytes=0' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stderr_logfile=/dev/stderr' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stderr_logfile_maxbytes=0' >> /etc/supervisor/conf.d/app.conf && \
    echo '' >> /etc/supervisor/conf.d/app.conf && \
    echo '[program:queue]' >> /etc/supervisor/conf.d/app.conf && \
    echo 'command=php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=1800' >> /etc/supervisor/conf.d/app.conf && \
    echo 'autostart=true' >> /etc/supervisor/conf.d/app.conf && \
    echo 'autorestart=true' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stdout_logfile=/dev/stdout' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stdout_logfile_maxbytes=0' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stderr_logfile=/dev/stderr' >> /etc/supervisor/conf.d/app.conf && \
    echo 'stderr_logfile_maxbytes=0' >> /etc/supervisor/conf.d/app.conf

EXPOSE 8080

CMD ["/app/start.sh"]
