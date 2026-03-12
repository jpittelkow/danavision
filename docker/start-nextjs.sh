#!/bin/sh
set -e

# Wait for frontend directory to be available (volume mount)
for i in 1 2 3 4 5; do
    if [ -d "/var/www/html/frontend" ]; then
        break
    fi
    sleep 1
done

cd /var/www/html/frontend

# Ensure .next directory exists with proper permissions for dev mode
# This handles the case where volume mount creates an empty .next volume
if [ ! -d ".next/cache" ]; then
    echo "Creating .next cache directory..."
    mkdir -p .next/cache 2>/dev/null || true
fi

# Check if standalone server.js exists (production standalone build)
if [ -f "server.js" ]; then
    echo "Starting Next.js in production mode (standalone)..."
    export NODE_ENV=production
    export HOSTNAME="0.0.0.0"
    export PORT=3000
    exec node server.js
# Check if .next build exists (BUILD_ID is created during production build)
elif [ -f ".next/BUILD_ID" ]; then
    echo "Starting Next.js in production mode..."
    export NODE_ENV=production
    exec npm start
else
    echo "Starting Next.js in development mode (no build found)..."
    
    # Set NODE_ENV for development
    export NODE_ENV=development
    
    # Set npm cache to a writable location (www-data user can't write to /var/www/.npm)
    export npm_config_cache=/tmp/.npm
    
    # Install dependencies if node_modules doesn't exist or is incomplete
    # This handles the case where volume mount creates an empty node_modules directory
    # Check for .bin/next specifically to ensure next is available
    if [ ! -f "node_modules/.bin/next" ]; then
        echo "Installing dependencies in container..."
        npm install --legacy-peer-deps
    fi
    
    exec npm run dev
fi
