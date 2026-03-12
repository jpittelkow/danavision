#!/bin/bash

# Verify Get Cooking setup was applied correctly.
# Usage: bash scripts/verify-setup.sh
#
# Checks app name, fonts, database config, Docker, and git.
# Exits 0 if all checks pass, 1 if any errors are found.

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ERRORS=0

pass() { echo -e "${GREEN}✓ $1${NC}"; }
fail() { echo -e "${RED}✗ $1${NC}"; ERRORS=$((ERRORS + 1)); }
warn() { echo -e "${YELLOW}⚠ $1${NC}"; }
header() { echo -e "\n${YELLOW}$1${NC}"; }

# ── App Name ──────────────────────────────────────────────────────────────────

check_app_name() {
    header "Checking app name..."

    local APP_NAME
    APP_NAME=$(grep "^NEXT_PUBLIC_APP_NAME=" .env.example 2>/dev/null | cut -d'=' -f2 | tr -d '"')

    if [ -z "$APP_NAME" ]; then
        fail "NEXT_PUBLIC_APP_NAME not found in .env.example"
        return
    fi

    echo "  App name: $APP_NAME"

    if [ "$APP_NAME" = "Sourdough" ]; then
        warn "App name is still 'Sourdough' — Tier 1 may not be complete"
    fi

    if grep -q "$APP_NAME" frontend/config/app.ts 2>/dev/null; then
        pass "App name in frontend/config/app.ts"
    else
        fail "App name not found in frontend/config/app.ts"
    fi

    if grep -q "$APP_NAME" backend/config/app.php 2>/dev/null; then
        pass "App name in backend/config/app.php"
    else
        fail "App name not found in backend/config/app.php"
    fi
}

# ── Fonts ─────────────────────────────────────────────────────────────────────

check_fonts() {
    header "Checking fonts..."

    if grep -qE "from 'next/font/google'|from 'geist/font" frontend/config/fonts.ts 2>/dev/null; then
        pass "Fonts configured in frontend/config/fonts.ts"
    else
        fail "Fonts not configured in frontend/config/fonts.ts"
    fi

    # Warn if Geist is imported but package may not be installed
    if grep -qF "from 'geist/font" frontend/config/fonts.ts 2>/dev/null; then
        if [ -d "frontend/node_modules/geist" ]; then
            pass "geist npm package installed"
        else
            fail "Geist font imported but 'geist' package not installed — run: docker exec sourdough-dev bash -c 'cd /var/www/html/frontend && npm install geist'"
        fi
    fi
}

# ── Database ──────────────────────────────────────────────────────────────────

check_database() {
    header "Checking database configuration..."

    local DB_CONN
    DB_CONN=$(grep "^DB_CONNECTION=" backend/.env.example 2>/dev/null | cut -d'=' -f2)

    if [ -z "$DB_CONN" ]; then
        fail "DB_CONNECTION not found in backend/.env.example"
        return
    fi

    echo "  Database: $DB_CONN"

    case "$DB_CONN" in
        sqlite|mysql|pgsql) pass "Valid database connection: $DB_CONN" ;;
        *) fail "Unknown database connection: $DB_CONN" ;;
    esac

    if [ "$DB_CONN" = "mysql" ] || [ "$DB_CONN" = "pgsql" ]; then
        if grep -qE "image: mysql|image: postgres" docker-compose.yml 2>/dev/null; then
            pass "Database service present in docker-compose.yml"
        else
            fail "DB_CONNECTION is $DB_CONN but no db service found in docker-compose.yml"
        fi
    fi
}

# ── Docker ────────────────────────────────────────────────────────────────────

check_docker() {
    header "Checking Docker..."

    if docker ps > /dev/null 2>&1; then
        pass "Docker is running"
    else
        fail "Docker is not running"
        return
    fi

    local CONTAINER_NAME
    CONTAINER_NAME=$(grep "^CONTAINER_NAME=" .env.example 2>/dev/null | cut -d'=' -f2)
    CONTAINER_NAME="${CONTAINER_NAME:-sourdough-dev}"
    if docker ps --format '{{.Names}}' | grep -q "$CONTAINER_NAME"; then
        pass "Dev container is running"
    else
        warn "Dev container not running — start with: docker-compose up -d"
    fi
}

# ── Git ───────────────────────────────────────────────────────────────────────

check_git() {
    header "Checking git..."

    if [ -d ".git" ] && git rev-parse --git-dir > /dev/null 2>&1; then
        pass "Git repository initialized"

        local REMOTE
        REMOTE=$(git remote get-url origin 2>/dev/null)
        if [ -n "$REMOTE" ]; then
            pass "Remote 'origin' set: $REMOTE"
        else
            warn "Remote 'origin' not set — run: git remote add origin <your-repo-url>"
        fi
    else
        warn ".git not found — Tier 3 git setup not completed yet"
    fi
}

# ── Stale References ─────────────────────────────────────────────────────────

check_stale_references() {
    header "Checking for stale 'Sourdough' references..."

    local APP_NAME
    APP_NAME=$(grep "^NEXT_PUBLIC_APP_NAME=" .env.example 2>/dev/null | cut -d'=' -f2 | tr -d '"')

    if [ -z "$APP_NAME" ] || [ "$APP_NAME" = "Sourdough" ]; then
        warn "Skipping stale reference check (app name not set or still 'Sourdough')"
        return
    fi

    # Check a few key locations for leftover "Sourdough" text
    local COUNT
    COUNT=$(grep -r "Sourdough" frontend/config/ backend/config/ .env.example 2>/dev/null | grep -v "\.git" | wc -l)

    if [ "$COUNT" -eq 0 ]; then
        pass "No 'Sourdough' references in key config files"
    else
        warn "$COUNT 'Sourdough' reference(s) still in config files — may need Tier 1 cleanup"
        grep -r "Sourdough" frontend/config/ backend/config/ .env.example 2>/dev/null | head -5
    fi
}

# ── Run All Checks ────────────────────────────────────────────────────────────

echo "Sourdough Setup Verification"
echo "============================"

check_app_name
check_fonts
check_database
check_docker
check_git
check_stale_references

# ── Summary ───────────────────────────────────────────────────────────────────

echo ""
echo "============================"
if [ "$ERRORS" -eq 0 ]; then
    echo -e "${GREEN}All checks passed!${NC}"
    exit 0
else
    echo -e "${RED}$ERRORS error(s) found — review the output above and fix before proceeding.${NC}"
    exit 1
fi
