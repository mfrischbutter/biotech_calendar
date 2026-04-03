#!/bin/sh
set -e

echo "Starting application..."

# Ensure SQLite database exists on persistent volume
if [ ! -f /data/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /data/database.sqlite
fi

# Ensure www-data can write to the database and its directory (needed for SQLite WAL)
chown www-data:www-data /data
chown www-data:www-data /data/database.sqlite
chmod 664 /data/database.sqlite

# Symlink database to expected location
ln -sf /data/database.sqlite /var/www/html/database/database.sqlite

# Ensure storage is writable
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

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

# Seed if database is empty (first deploy)
if [ "$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null)" = "0" ]; then
    echo "Seeding database..."
    php artisan db:seed --force
fi

echo "Application ready."

exec "$@"
