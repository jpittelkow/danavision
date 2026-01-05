#!/bin/sh
# Safe test runner - ensures tests use in-memory database
# This script must be used to run tests in Docker containers
#
# Usage:
#   docker compose exec danavision ./scripts/run-tests.sh
#   docker compose exec danavision ./scripts/run-tests.sh --filter="dashboard"
#   docker compose exec danavision ./scripts/run-tests.sh tests/Feature/DashboardTest.php

set -e

# Change to the application directory
cd /var/www/html

# Override environment variables to use in-memory database
# These take precedence over docker-compose.yml environment variables
export APP_ENV=testing
export DB_DATABASE=:memory:
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export BCRYPT_ROUNDS=4

echo "======================================"
echo " DanaVision Safe Test Runner"
echo "======================================"
echo ""
echo "Test configuration:"
echo "  APP_ENV=$APP_ENV"
echo "  DB_DATABASE=$DB_DATABASE (in-memory - production data protected)"
echo ""

# Run pest with all arguments passed through
exec ./vendor/bin/pest "$@"
