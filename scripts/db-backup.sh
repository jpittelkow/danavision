#!/bin/bash
# DanaVision Database Backup Script
#
# Usage:
#   ./scripts/db-backup.sh backup    # Create a backup
#   ./scripts/db-backup.sh restore   # Restore from latest backup
#   ./scripts/db-backup.sh list      # List available backups

set -e

BACKUP_DIR="./backups"
CONTAINER_NAME="danavision"
DB_PATH="/var/www/html/database/database.sqlite"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

case "$1" in
    backup)
        BACKUP_FILE="$BACKUP_DIR/database_${TIMESTAMP}.sqlite"
        echo "📦 Creating backup..."
        docker cp "$CONTAINER_NAME:$DB_PATH" "$BACKUP_FILE"
        
        # Also create a 'latest' symlink
        ln -sf "database_${TIMESTAMP}.sqlite" "$BACKUP_DIR/database_latest.sqlite"
        
        echo "✅ Backup created: $BACKUP_FILE"
        echo "   Size: $(ls -lh "$BACKUP_FILE" | awk '{print $5}')"
        ;;
        
    restore)
        if [ -z "$2" ]; then
            RESTORE_FILE="$BACKUP_DIR/database_latest.sqlite"
        else
            RESTORE_FILE="$2"
        fi
        
        if [ ! -f "$RESTORE_FILE" ]; then
            echo "❌ Backup file not found: $RESTORE_FILE"
            exit 1
        fi
        
        echo "⚠️  WARNING: This will overwrite the current database!"
        read -p "Are you sure? (yes/no): " confirm
        
        if [ "$confirm" = "yes" ]; then
            echo "📦 Restoring from: $RESTORE_FILE"
            docker cp "$RESTORE_FILE" "$CONTAINER_NAME:$DB_PATH"
            echo "✅ Database restored!"
            echo "   Restarting container..."
            docker restart "$CONTAINER_NAME"
        else
            echo "❌ Restore cancelled"
        fi
        ;;
        
    list)
        echo "📋 Available backups:"
        ls -lh "$BACKUP_DIR"/*.sqlite 2>/dev/null || echo "   No backups found"
        ;;
        
    *)
        echo "DanaVision Database Backup Tool"
        echo ""
        echo "Usage:"
        echo "  $0 backup              Create a new backup"
        echo "  $0 restore [file]      Restore from backup (defaults to latest)"
        echo "  $0 list                List available backups"
        echo ""
        echo "Backups are stored in: $BACKUP_DIR/"
        ;;
esac
