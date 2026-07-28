#!/bin/bash

#######################################
# Tuti Production Deployment Script
# 
# This script automates the deployment process:
# - Pulls latest code from git
# - Installs dependencies
# - Runs migrations
# - Seeds idempotent defaults (e.g. Coordinadora package types)
# - Fixes storage symlinks
# - Sets permissions
# - Clears caches
# - Signals queue workers to reload code
# - Optionally restarts services (--full)
#######################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse command line arguments
BRANCH="stage"
RESTART_SERVICES=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --full)
            RESTART_SERVICES=true
            shift
            ;;
        --services)
            RESTART_SERVICES=true
            shift
            ;;
        *)
            BRANCH="$1"
            shift
            ;;
    esac
done

# Configuration
PHP_VERSION="${PHP_VERSION:-8.1}"
WEB_USER="${WEB_USER:-www-data}"

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Tuti Deployment Script              ║${NC}"
echo -e "${BLUE}║   Branch: ${BRANCH}                          ║${NC}"
if [ "$RESTART_SERVICES" = true ]; then
    echo -e "${BLUE}║   Mode: Full (with service restarts)  ║${NC}"
else
    echo -e "${BLUE}║   Mode: Standard (no service restarts)║${NC}"
fi
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""

# Function to print step headers
print_step() {
    echo -e "\n${BLUE}▶ $1${NC}"
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print warnings
print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Function to print errors
print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Error: artisan file not found. Are you in the project root?"
    exit 1
fi

# 1. Enable Maintenance Mode
print_step "Enabling maintenance mode..."
php artisan down --retry=60 || print_warning "Maintenance mode already enabled or failed"
print_success "Maintenance mode enabled"

# 2. Pull Latest Changes
print_step "Pulling latest changes from git ($BRANCH)..."
git fetch origin
if git diff --quiet HEAD origin/$BRANCH; then
    print_warning "No changes detected in remote $BRANCH"
else
    git pull origin $BRANCH
    print_success "Code updated from $BRANCH"
fi

# 3. Install/Update Composer Dependencies
print_step "Installing/Updating Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
print_success "Composer dependencies installed"

# 4. Run Database Migrations
print_step "Running database migrations..."
php artisan migrate --force
print_success "Migrations completed"

# 5. Seed idempotent defaults required by Última Milla / Coordinadora
# PackageTypeSeeder uses firstOrCreate — safe to re-run; does not overwrite admin edits.
print_step "Seeding Coordinadora package types (idempotent)..."
php artisan db:seed --class=PackageTypeSeeder --force
print_success "Package types seeded (existing rows left unchanged)"

# 6. Fix Storage Symlink (CRITICAL for images)
print_step "Fixing storage symlink..."
php artisan storage:link --force
print_success "Storage symlink created"

# 7. Ensure Laravel Storage Structure Exists
print_step "Ensuring Laravel storage structure..."
# Create all required Laravel storage directories
mkdir -p storage/app/public
mkdir -p storage/app/public/products
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
print_success "Storage structure verified"

# 8. Set Proper Permissions
print_step "Setting file permissions..."
chmod -R 775 storage bootstrap/cache
chown -R $WEB_USER:$WEB_USER storage bootstrap/cache public/storage 2>/dev/null || print_warning "Could not change ownership (may need sudo)"
print_success "Permissions set"

# 9. Clear All Caches
print_step "Clearing application caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Manually remove cached files to ensure complete cache clear
print_step "Removing cached files manually..."
rm -rf storage/framework/cache/data/* 2>/dev/null || true
rm -rf storage/framework/views/* 2>/dev/null || true
rm -rf bootstrap/cache/*.php 2>/dev/null || true
print_success "Caches cleared completely"

# 10. Rebuild Caches
print_step "Rebuilding application caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
print_success "Caches rebuilt"

# 11. Signal queue workers to pick up new job/schedule code
# Always run: SyncZoneRuteros / BulkSync / Coordinadora processing jobs must reload after deploy.
print_step "Signaling queue workers to restart..."
php artisan queue:restart || print_warning "queue:restart failed (workers may still be on old code)"
print_success "Queue restart signal sent"

# 12. Restart Queue Workers (optional with --full flag)
if [ "$RESTART_SERVICES" = true ]; then
    print_step "Restarting queue workers via Supervisor..."
    if command -v supervisorctl &> /dev/null; then
        sudo supervisorctl restart all || print_warning "Could not restart supervisor (may need proper sudo permissions)"
        print_success "Supervisor workers restarted"
    else
        print_warning "supervisorctl not available (queue:restart signal already sent)"
    fi
else
    print_step "Skipping Supervisor restart (use --full to force supervisorctl restart)"
fi

# 13. Restart Web Services (optional with --full flag)
if [ "$RESTART_SERVICES" = true ]; then
    print_step "Restarting web services..."
    if [ -x "$(command -v systemctl)" ]; then
        # Try to restart PHP-FPM
        sudo systemctl restart php${PHP_VERSION}-fpm 2>/dev/null && print_success "PHP-FPM restarted" || print_warning "Could not restart PHP-FPM"
        
        # Try to restart Nginx
        sudo systemctl restart nginx 2>/dev/null && print_success "Nginx restarted" || print_warning "Could not restart Nginx"
    else
        print_warning "systemctl not available, skipping service restart"
    fi
else
    print_step "Skipping web service restart (use --full flag to restart services)"
fi

# 14. Verify Storage Structure
print_step "Verifying storage structure..."
if [ -L "public/storage" ] && [ -d "storage/app/public" ]; then
    print_success "Storage symlink verified"
else
    print_error "Storage symlink verification failed!"
    ls -la public/storage
fi

# 15. Disable Maintenance Mode
print_step "Disabling maintenance mode..."
php artisan up
print_success "Application is now live!"

# Final Summary
echo ""
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   Deployment Completed Successfully!  ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Summary:${NC}"
echo "  - Branch deployed: $BRANCH"
echo "  - Storage symlink: $(readlink public/storage)"
echo "  - Git HEAD: $(git rev-parse --short HEAD)"
echo "  - Last commit: $(git log -1 --pretty=format:'%s')"
echo ""
echo -e "${YELLOW}Post-deploy checks (Última Milla / Coordinadora):${NC}"
echo "  - Confirm Coordinadora + FV env vars in .env (see docs/coordinadora-48h-stage-checklist.md)"
echo "  - Confirm express 48H enabled and force_delivery_date_enabled is OFF in admin settings"
echo "  - Confirm scheduler cron is active (daily zone rutero sync at 04:15)"
echo "  - Smoke: quote → express order → FV + guía; Mi Ruta → Agregar sucursal"
echo ""
echo -e "${YELLOW}Important:${NC}"
echo "  - Test the application in your browser"
echo "  - Check that images are displaying correctly"
echo "  - Monitor logs: tail -f storage/logs/laravel.log"
if [ "$RESTART_SERVICES" = false ]; then
    echo ""
    echo -e "${YELLOW}Note: Web/Supervisor services were NOT hard-restarted.${NC}"
    echo "  - Queue workers received queue:restart (they reload after current job)"
    echo "  - If you need a hard restart, run: bash deploy.sh --full"
    echo "  - Or manually: sudo systemctl restart php${PHP_VERSION}-fpm nginx"
fi
echo ""
