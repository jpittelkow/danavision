#!/bin/sh
set -e

echo "Starting DanaVision..."

# Ensure database directory exists and has correct permissions
mkdir -p /var/www/html/database
chown -R www-data:www-data /var/www/html/database

# Create SQLite database if it doesn't exist
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Ensure storage directories exist and have correct permissions
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage

# Run migrations
echo "Running database migrations..."
php /var/www/html/artisan migrate --force

# Clear and cache config for production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php /var/www/html/artisan config:cache
    php /var/www/html/artisan route:cache
    php /var/www/html/artisan view:cache
fi

echo "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
