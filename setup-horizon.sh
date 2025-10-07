#!/bin/bash

# Laravel Horizon Setup Script for Stage Environment
# This script helps set up Horizon to match the master branch configuration

set -e

echo "🚀 Laravel Horizon Setup Script"
echo "================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Redis is running
echo "📡 Checking Redis connection..."
if redis-cli ping > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Redis is running"
else
    echo -e "${RED}✗${NC} Redis is not running!"
    echo ""
    echo "Please start Redis first:"
    echo "  macOS: brew services start redis"
    echo "  Ubuntu: sudo systemctl start redis-server"
    echo "  Docker: docker run -d -p 6379:6379 redis:alpine"
    exit 1
fi

# Check current QUEUE_CONNECTION
echo ""
echo "🔍 Checking current queue configuration..."
CURRENT_QUEUE=$(grep "QUEUE_CONNECTION=" .env | cut -d '=' -f2)
echo "Current QUEUE_CONNECTION: $CURRENT_QUEUE"

if [ "$CURRENT_QUEUE" = "redis" ]; then
    echo -e "${GREEN}✓${NC} Already configured to use Redis"
else
    echo -e "${YELLOW}!${NC} Updating QUEUE_CONNECTION to redis..."
    
    # Backup .env
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    echo "Created backup of .env"
    
    # Update QUEUE_CONNECTION
    if grep -q "QUEUE_CONNECTION=" .env; then
        sed -i.bak 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env
        rm .env.bak 2>/dev/null || true
        echo -e "${GREEN}✓${NC} Updated QUEUE_CONNECTION to redis"
    else
        echo "QUEUE_CONNECTION=redis" >> .env
        echo -e "${GREEN}✓${NC} Added QUEUE_CONNECTION=redis to .env"
    fi
fi

# Clear and optimize
echo ""
echo "🧹 Clearing configuration cache..."
php artisan config:clear
php artisan cache:clear
echo -e "${GREEN}✓${NC} Cache cleared"

# Check if Horizon assets are published
echo ""
echo "📦 Checking Horizon assets..."
if [ ! -d "public/vendor/horizon" ]; then
    echo "Publishing Horizon assets..."
    php artisan horizon:install
    echo -e "${GREEN}✓${NC} Horizon assets published"
else
    echo -e "${GREEN}✓${NC} Horizon assets already published"
fi

# Run migrations (for jobs table, if needed)
echo ""
echo "🗄️  Checking database..."
php artisan migrate --force
echo -e "${GREEN}✓${NC} Migrations completed"

echo ""
echo "================================"
echo -e "${GREEN}✓ Setup Complete!${NC}"
echo ""
echo "📋 Next Steps:"
echo ""
echo "1. Start Horizon:"
echo "   ${YELLOW}php artisan horizon${NC}"
echo ""
echo "2. Access Horizon Dashboard:"
echo "   ${YELLOW}http://your-domain/horizon${NC}"
echo ""
echo "3. Test inventory sync - it should now work without timeout!"
echo ""
echo "For production deployment with Supervisor, see HORIZON-SETUP.md"
echo ""

