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

# Ensure data directory exists with permissive permissions
# Using 777/666 to work with any bind mount (Unraid, Portainer, etc.)
echo "Setting up data directory..."
mkdir -p /var/www/html/data
chmod 777 /var/www/html/data

# Check if database volume is properly mounted (look for .volume_marker file)
VOLUME_MARKER="/var/www/html/data/.volume_marker"
DB_FILE="/var/www/html/data/database.sqlite"

# Production safety check - only block if there's risk of DATA LOSS
# (i.e., volume marker exists indicating previous data, but database is missing/empty)
if [ "$APP_ENV" = "production" ]; then
    echo "⚠️  PRODUCTION MODE - Database safety checks enabled"
    
    if [ -f "$VOLUME_MARKER" ] && [ ! -f "$DB_FILE" ]; then
        # Volume marker exists but no database = DATA LOSS RISK
        echo ""
        echo "❌ ERROR: Volume marker exists but database is missing!"
        echo "   - Volume marker found at: $VOLUME_MARKER"
        echo "   - No database found at: $DB_FILE"
        echo ""
        echo "   This indicates a previously initialized database that is now missing."
        echo "   Your data may have been lost due to volume misconfiguration."
        echo ""
        echo "   To create a fresh database, set ALLOW_DB_INIT=true"
        echo ""
        
        if [ "$ALLOW_DB_INIT" != "true" ]; then
            echo "❌ Refusing to create new database - previous data may be recoverable"
            echo "   Exiting to prevent data loss."
            exit 1
        else
            echo "⚠️  ALLOW_DB_INIT=true is set - proceeding with new database creation"
        fi
    elif [ ! -f "$VOLUME_MARKER" ] && [ ! -f "$DB_FILE" ]; then
        # Fresh install - no marker, no database = safe to initialize
        echo "📦 Fresh installation detected - initializing new database"
    fi
fi

# Create SQLite database if it doesn't exist
if [ ! -f "$DB_FILE" ]; then
    echo "📦 Creating new SQLite database..."
    touch "$DB_FILE"
    chmod 666 "$DB_FILE"
    
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

# Make everything in data directory writable (running as root makes this work on bind mounts)
echo "Setting permissions on data directory..."
chmod -R 777 /var/www/html/data
echo "Data directory contents:"
ls -la /var/www/html/data/

# Ensure storage directories exist and have correct permissions
echo "Setting up storage directories..."
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
chmod -R 777 /var/www/html/storage 2>/dev/null || true

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
