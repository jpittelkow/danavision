#!/bin/sh
set -e

cat << 'EOF'

  ____                __     ___     _             
 |  _ \  __ _ _ __   __ \ \   / (_)___(_) ___  _ __  
 | | | |/ _` | '_ \ / _` \ \ / /| / __| |/ _ \| '_ \ 
 | |_| | (_| | | | | (_| |\ V / | \__ \ | (_) | | | |
 |____/ \__,_|_| |_|\__,_| \_/  |_|___/_|\___/|_| |_|
                                                     
        💜 Smart Shopping Price Tracker 💜

EOF

echo "=== Starting DanaVision ==="

# Ensure database directory exists and has correct permissions
echo "Setting up database directory..."
mkdir -p /var/www/html/database
chown -R www-data:www-data /var/www/html/database

# Create SQLite database if it doesn't exist
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Ensure storage directories exist and have correct permissions
echo "Setting up storage directories..."
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage

# Run migrations
echo "Running database migrations..."

DB_FILE="/var/www/html/database/database.sqlite"

# Check if database is in a corrupt state (migrations table exists but users table doesn't)
if [ -f "$DB_FILE" ]; then
    HAS_MIGRATIONS=$(sqlite3 "$DB_FILE" "SELECT name FROM sqlite_master WHERE type='table' AND name='migrations';" 2>/dev/null || echo "")
    HAS_USERS=$(sqlite3 "$DB_FILE" "SELECT name FROM sqlite_master WHERE type='table' AND name='users';" 2>/dev/null || echo "")
    
    if [ -n "$HAS_MIGRATIONS" ] && [ -z "$HAS_USERS" ]; then
        echo "WARNING: Detected corrupt migration state (migrations table exists but users table missing)"
        echo "Running fresh migrations to fix..."
        php /var/www/html/artisan migrate:fresh --force --verbose
    else
        php /var/www/html/artisan migrate --force --verbose
    fi
else
    # Fresh database, just run migrations
    php /var/www/html/artisan migrate --force --verbose
fi

echo "Migrations complete."

# Clear and cache config for production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration for production..."
    php /var/www/html/artisan config:cache
    php /var/www/html/artisan route:cache
    php /var/www/html/artisan view:cache
fi

echo "=== Starting supervisord ==="
exec /usr/bin/supervisord -c /etc/supervisord.conf
