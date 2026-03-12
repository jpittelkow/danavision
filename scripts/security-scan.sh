#!/bin/bash
#
# Security Scan Script
# Runs all SAST tools locally before pushing to CI
#
# Usage: ./scripts/security-scan.sh [--fix] [--quick]
#   --fix   Auto-fix ESLint issues where possible
#   --quick Skip Semgrep (faster, but less comprehensive)
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments
FIX_MODE=false
QUICK_MODE=false
for arg in "$@"; do
    case $arg in
        --fix)
            FIX_MODE=true
            ;;
        --quick)
            QUICK_MODE=true
            ;;
    esac
done

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}       Security Scan${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

FAILED=0

# -----------------------------
# Backend: PHPStan
# -----------------------------
echo -e "${YELLOW}[1/3] Running PHPStan (Backend)...${NC}"
cd "$PROJECT_ROOT/backend"

if [ -f "vendor/bin/phpstan" ]; then
    if ./vendor/bin/phpstan analyse --memory-limit=512M; then
        echo -e "${GREEN}PHPStan: PASSED${NC}"
    else
        echo -e "${RED}PHPStan: FAILED${NC}"
        FAILED=1
    fi
else
    echo -e "${YELLOW}PHPStan not installed. Run 'composer install' in backend/ first.${NC}"
    FAILED=1
fi
echo ""

# -----------------------------
# Frontend: ESLint with Security Plugin
# -----------------------------
echo -e "${YELLOW}[2/3] Running ESLint Security (Frontend)...${NC}"
cd "$PROJECT_ROOT/frontend"

if [ -d "node_modules" ]; then
    ESLINT_CMD="npm run lint"
    if [ "$FIX_MODE" = true ]; then
        ESLINT_CMD="npm run lint -- --fix"
    fi
    
    if $ESLINT_CMD; then
        echo -e "${GREEN}ESLint Security: PASSED${NC}"
    else
        echo -e "${RED}ESLint Security: FAILED${NC}"
        FAILED=1
    fi
else
    echo -e "${YELLOW}Node modules not installed. Run 'npm install' in frontend/ first.${NC}"
    FAILED=1
fi
echo ""

# -----------------------------
# Semgrep (Both Backend & Frontend)
# -----------------------------
if [ "$QUICK_MODE" = false ]; then
    echo -e "${YELLOW}[3/3] Running Semgrep (Full Codebase)...${NC}"
    cd "$PROJECT_ROOT"
    
    if command -v semgrep &> /dev/null; then
        if semgrep scan --config=p/security-audit --config=p/php-laravel --config=p/typescript --config=p/owasp-top-ten .; then
            echo -e "${GREEN}Semgrep: PASSED${NC}"
        else
            echo -e "${RED}Semgrep: FAILED${NC}"
            FAILED=1
        fi
    else
        echo -e "${YELLOW}Semgrep not installed. Install with: pip install semgrep${NC}"
        echo -e "${YELLOW}Or run with --quick to skip Semgrep.${NC}"
    fi
else
    echo -e "${YELLOW}[3/3] Skipping Semgrep (--quick mode)${NC}"
fi
echo ""

# -----------------------------
# Summary
# -----------------------------
echo -e "${BLUE}========================================${NC}"
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All security checks PASSED!${NC}"
    exit 0
else
    echo -e "${RED}Some security checks FAILED.${NC}"
    echo -e "Fix the issues above before pushing."
    exit 1
fi
