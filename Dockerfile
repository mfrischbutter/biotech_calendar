# Stage 1: Install PHP dependencies (needed by frontend for Ziggy)
FROM php:8.4-cli-alpine AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Stage 2: Build frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js tsconfig.json components.json ./
COPY resources/ resources/
# Copy Ziggy from vendor stage (needed for import in app.ts)
COPY --from=vendor /app/vendor/tightenco/ziggy vendor/tightenco/ziggy
RUN npm run build

# Stage 3: Production PHP image
FROM php:8.4-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    curl \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo_sqlite pdo_mysql mbstring zip bcmath opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

# Copy vendor from composer stage
COPY --from=vendor /app/vendor vendor
COPY composer.json composer.lock ./

# Copy application code
COPY . .

# Copy built frontend assets from frontend stage
COPY --from=frontend /app/public/build public/build

# Finish composer setup
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create directory for SQLite database on persistent volume
RUN mkdir -p /data && chown www-data:www-data /data

# Copy entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
