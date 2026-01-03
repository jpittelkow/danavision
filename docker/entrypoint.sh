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
echo "Environment: ${APP_ENV:-local}"

# Ensure database directory exists and has correct permissions
echo "Setting up database directory..."
mkdir -p /var/www/html/database
chown -R www-data:www-data /var/www/html/database

# Check if database volume is properly mounted (look for .volume_marker file)
VOLUME_MARKER="/var/www/html/database/.volume_marker"
DB_FILE="/var/www/html/database/database.sqlite"

# Production safety check
if [ "$APP_ENV" = "production" ]; then
    echo "⚠️  PRODUCTION MODE - Database safety checks enabled"
    
    # Check if this looks like a fresh/unmounted volume
    if [ ! -f "$VOLUME_MARKER" ] && [ ! -f "$DB_FILE" ]; then
        echo "❌ ERROR: Database volume appears to be empty or not mounted!"
        echo "   - No volume marker found at: $VOLUME_MARKER"
        echo "   - No database found at: $DB_FILE"
        echo ""
        echo "   This usually means:"
        echo "   1. The Docker volume was deleted (docker compose down -v)"
        echo "   2. The volume is not properly mounted"
        echo "   3. This is a fresh deployment that needs manual database setup"
        echo ""
        echo "   To initialize a new production database, set ALLOW_DB_INIT=true"
        echo "   Example: docker run -e ALLOW_DB_INIT=true ..."
        echo ""
        
        if [ "$ALLOW_DB_INIT" != "true" ]; then
            echo "❌ Refusing to create new database in production without ALLOW_DB_INIT=true"
            echo "   Exiting to prevent data loss."
            exit 1
        else
            echo "⚠️  ALLOW_DB_INIT=true is set - proceeding with new database creation"
        fi
    fi
fi

# Create SQLite database if it doesn't exist
if [ ! -f "$DB_FILE" ]; then
    echo "📦 Creating new SQLite database..."
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
    
    # Create volume marker to track that this volume has been initialized
    echo "Initialized: $(date -Iseconds)" > "$VOLUME_MARKER"
    echo "   Created volume marker at: $VOLUME_MARKER"
else
    echo "✅ Existing database found at: $DB_FILE"
    DB_SIZE_BYTES=$(stat -c%s "$DB_FILE" 2>/dev/null || stat -f%z "$DB_FILE" 2>/dev/null || echo "0")
    DB_SIZE_HUMAN=$(ls -lh "$DB_FILE" | awk '{print $5}')
    echo "   Database size: $DB_SIZE_HUMAN ($DB_SIZE_BYTES bytes)"
    
    # CRITICAL: Check if database is empty (0 bytes) in production
    if [ "$APP_ENV" = "production" ] && [ "$DB_SIZE_BYTES" = "0" ]; then
        echo ""
        echo "❌ ERROR: Database file exists but is EMPTY (0 bytes)!"
        echo "   This usually means the volume mount created an empty file."
        echo ""
        echo "   Your data may be at a different location. Check:"
        echo "   - /mnt/user/appdata/danavision/database/database.sqlite (old path)"
        echo "   - Ensure the host file exists BEFORE starting the container"
        echo ""
        echo "   To initialize a new database anyway, set ALLOW_DB_INIT=true"
        echo ""
        
        if [ "$ALLOW_DB_INIT" != "true" ]; then
            echo "❌ Refusing to run migrations on empty database in production"
            echo "   Exiting to prevent data loss."
            exit 1
        else
            echo "⚠️  ALLOW_DB_INIT=true is set - proceeding with empty database"
        fi
    fi
    
    # Ensure volume marker exists for existing databases
    if [ ! -f "$VOLUME_MARKER" ]; then
        echo "Migrated: $(date -Iseconds)" > "$VOLUME_MARKER"
        echo "   Created volume marker for existing database"
    fi
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
php /var/www/html/artisan migrate --force --verbose
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
