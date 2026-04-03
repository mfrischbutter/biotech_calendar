#!/bin/sh
set -e

echo "Starting application..."

# Ensure SQLite database exists on persistent volume
if [ ! -f /data/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /data/database.sqlite
    chown www-data:www-data /data/database.sqlite
fi

# Symlink database to expected location
ln -sf /data/database.sqlite /var/www/html/database/database.sqlite

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY not set. Generating one..."
    php artisan key:generate --force
fi

# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "Running migrations..."
php artisan migrate --force

echo "Application ready."

exec "$@"
