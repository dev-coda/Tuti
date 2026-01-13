#!/bin/bash

#######################################
# Tuti Production Deployment Script
# 
# This script automates the deployment process:
# - Pulls latest code from git
# - Installs dependencies
# - Runs migrations
# - Fixes storage symlinks
# - Sets permissions
# - Clears caches
# - Restarts services
#######################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
BRANCH="${1:-stage}"  # Default to stage branch, can be overridden
PHP_VERSION="${PHP_VERSION:-8.1}"
WEB_USER="${WEB_USER:-www-data}"

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Tuti Deployment Script              ║${NC}"
echo -e "${BLUE}║   Branch: ${BRANCH}                          ║${NC}"
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

# 5. Fix Storage Symlink (CRITICAL for images)
print_step "Fixing storage symlink..."
# Remove old symlink if it exists
if [ -L "public/storage" ]; then
    rm public/storage
fi
php artisan storage:link --force
print_success "Storage symlink created"

# 6. Set Proper Permissions
print_step "Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chown -R $WEB_USER:$WEB_USER storage bootstrap/cache public/storage 2>/dev/null || print_warning "Could not change ownership (may need sudo)"
print_success "Permissions set"

# 7. Clear All Caches
print_step "Clearing application caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
print_success "Caches cleared"

# 8. Rebuild Caches
print_step "Rebuilding application caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
print_success "Caches rebuilt"

# 9. Restart Queue Workers
print_step "Restarting queue workers..."
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl restart all || print_warning "Could not restart supervisor (may need proper sudo permissions)"
    print_success "Supervisor workers restarted"
else
    php artisan queue:restart || print_warning "Queue restart command executed (workers will restart on next job)"
    print_success "Queue restart signal sent"
fi

# 10. Restart Web Services
print_step "Restarting web services..."
if [ -x "$(command -v systemctl)" ]; then
    # Try to restart PHP-FPM
    sudo systemctl restart php${PHP_VERSION}-fpm 2>/dev/null && print_success "PHP-FPM restarted" || print_warning "Could not restart PHP-FPM"
    
    # Try to restart Nginx
    sudo systemctl restart nginx 2>/dev/null && print_success "Nginx restarted" || print_warning "Could not restart Nginx"
else
    print_warning "systemctl not available, skipping service restart"
fi

# 11. Verify Storage Structure
print_step "Verifying storage structure..."
if [ -L "public/storage" ] && [ -d "storage/app/public" ]; then
    print_success "Storage symlink verified"
else
    print_error "Storage symlink verification failed!"
    ls -la public/storage
fi

# 12. Run Post-Deployment Tests (optional)
print_step "Running post-deployment checks..."

# Check if key directories exist
REQUIRED_DIRS=("storage/app/public" "storage/app/public/products" "storage/logs" "storage/framework/cache")
for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        print_success "Directory exists: $dir"
    else
        print_warning "Directory missing: $dir (creating...)"
        mkdir -p "$dir"
        chmod 755 "$dir"
    fi
done

# 13. Disable Maintenance Mode
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
echo -e "${YELLOW}Important:${NC}"
echo "  - Test the application in your browser"
echo "  - Check that images are displaying correctly"
echo "  - Monitor logs: tail -f storage/logs/laravel.log"
echo ""
