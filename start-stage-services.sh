#!/bin/bash

#######################################
# Stage Environment Services Startup Script
# 
# This script runs on the remote Linux stage server and:
# - Checks and starts PostgreSQL database service if needed
# - Verifies database connection is working
# - Checks and starts Redis if needed
# - Checks and starts Horizon if needed
# - Verifies all services are working
#
# Usage:
#   ./start-stage-services.sh
#######################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"
HORIZON_TIMEOUT=5  # Timeout in seconds for Horizon status check

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

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Stage Services Startup Script       ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""

# ============================================
# 1. Check and Start Database Service
# ============================================
print_step "Checking database service..."

# Function to check if PostgreSQL service is running
check_db_service() {
    # Linux - check systemctl
    if command -v systemctl &> /dev/null; then
        # Try different PostgreSQL service names
        for service in postgresql postgres; do
            if systemctl is-active --quiet $service 2>/dev/null; then
                echo "$service"
                return 0
            fi
        done
    fi
    # Fallback: check if postgres process is running
    if pgrep -x postgres > /dev/null 2>&1; then
        echo "running"
        return 0
    fi
    return 1
}

# Check if database service is running
DB_SERVICE_STATUS=$(check_db_service)
if [ -n "$DB_SERVICE_STATUS" ]; then
    print_success "Database service is running ($DB_SERVICE_STATUS)"
else
    print_warning "Database service is not running!"
    echo ""
    echo "Attempting to start database service..."
    
    DB_STARTED=false
    
    # Linux - try to start with systemctl
    if command -v systemctl &> /dev/null; then
        for service in postgresql postgres; do
            if systemctl list-unit-files | grep -q "^${service}.service"; then
                if sudo systemctl start $service 2>/dev/null; then
                    sleep 3  # Give PostgreSQL time to start
                    if systemctl is-active --quiet $service 2>/dev/null; then
                        print_success "Database service started via systemctl ($service)"
                        DB_STARTED=true
                        break
                    fi
                fi
            fi
        done
    fi
    
    if [ "$DB_STARTED" = false ]; then
        print_error "Could not start database service automatically"
        echo "Please start the database service manually:"
        echo "  sudo systemctl start postgresql"
        echo "  or: sudo systemctl start postgres"
        exit 1
    fi
fi

# Wait a bit for database to be fully ready
sleep 2

# ============================================
# 2. Verify Database Connection
# ============================================
print_step "Verifying database connection..."

# Test database connection using Laravel
MAX_RETRIES=5
RETRY_COUNT=0
DB_CONNECTED=false

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if php artisan db:show >/dev/null 2>&1; then
        DB_CONNECTED=true
        break
    fi
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        print_warning "Database connection attempt $RETRY_COUNT failed, retrying..."
        sleep 2
    fi
done

if [ "$DB_CONNECTED" = true ]; then
    print_success "Database connection successful"
    
    # Try to get database name
    DB_NAME=$(php artisan tinker --execute="echo config('database.connections.pgsql.database');" 2>/dev/null | grep -v "^>" | head -1 || echo "unknown")
    DB_HOST=$(php artisan tinker --execute="echo config('database.connections.pgsql.host');" 2>/dev/null | grep -v "^>" | head -1 || echo "unknown")
    print_success "Connected to database: $DB_NAME on $DB_HOST"
else
    print_error "Database connection failed after $MAX_RETRIES attempts!"
    print_warning "Please check your .env file database configuration:"
    print_warning "  - DB_HOST"
    print_warning "  - DB_PORT"
    print_warning "  - DB_DATABASE"
    print_warning "  - DB_USERNAME"
    print_warning "  - DB_PASSWORD"
    print_warning ""
    print_warning "Also verify the database service is running:"
    print_warning "  sudo systemctl status postgresql"
    exit 1
fi

# ============================================
# 3. Check and Start Redis
# ============================================
print_step "Checking Redis service..."

if redis-cli -h $REDIS_HOST -p $REDIS_PORT ping > /dev/null 2>&1; then
    print_success "Redis is running on $REDIS_HOST:$REDIS_PORT"
else
    print_warning "Redis is not running!"
    echo ""
    echo "Attempting to start Redis..."
    
    # Linux - start Redis with systemctl
    if command -v systemctl &> /dev/null; then
        if sudo systemctl start redis-server 2>/dev/null || sudo systemctl start redis 2>/dev/null; then
            sleep 2
            if redis-cli -h $REDIS_HOST -p $REDIS_PORT ping > /dev/null 2>&1; then
                print_success "Redis started via systemctl"
            else
                print_error "Failed to start Redis via systemctl"
                echo "Please start Redis manually: sudo systemctl start redis-server"
                exit 1
            fi
        else
            print_error "Could not start Redis via systemctl"
            echo "Please start Redis manually: sudo systemctl start redis-server"
            exit 1
        fi
    else
        print_error "systemctl not found. Please start Redis manually."
        exit 1
    fi
fi

# Test Redis connection from Laravel
print_step "Testing Redis connection from Laravel..."
if php artisan tinker --execute="echo Redis::ping();" 2>/dev/null | grep -q "PONG"; then
    print_success "Laravel can connect to Redis"
else
    print_warning "Laravel Redis connection test failed (check REDIS_HOST and REDIS_PORT in .env)"
fi

# ============================================
# 4. Check and Start Horizon
# ============================================
print_step "Checking Horizon service..."

# Check if Horizon is running
HORIZON_PID=$(pgrep -f "artisan horizon" || echo "")
if [ -n "$HORIZON_PID" ]; then
    print_success "Horizon is running (PID: $HORIZON_PID)"
    
    # Check Horizon status via artisan command
    if timeout $HORIZON_TIMEOUT php artisan horizon:status > /dev/null 2>&1; then
        print_success "Horizon status check passed"
    else
        print_warning "Horizon process exists but status check failed"
    fi
else
    print_warning "Horizon is not running"
    echo ""
    echo "Starting Horizon..."
    
    # Check if QUEUE_CONNECTION is set to redis
    if grep -q "QUEUE_CONNECTION=redis" .env 2>/dev/null; then
        print_success "Queue connection is set to redis"
    else
        print_warning "QUEUE_CONNECTION is not set to redis in .env"
        print_warning "Horizon requires QUEUE_CONNECTION=redis"
        print_warning "Update .env and run: php artisan config:clear"
    fi
    
    # Start Horizon in background
    nohup php artisan horizon > storage/logs/horizon.log 2>&1 &
    HORIZON_NEW_PID=$!
    sleep 3
    
    # Check if Horizon started successfully
    if ps -p $HORIZON_NEW_PID > /dev/null 2>&1; then
        print_success "Horizon started (PID: $HORIZON_NEW_PID)"
        print_warning "Horizon is running in background. Logs: storage/logs/horizon.log"
        print_warning "To stop Horizon: php artisan horizon:terminate"
    else
        print_error "Failed to start Horizon"
        echo "Check logs: tail -f storage/logs/horizon.log"
        exit 1
    fi
fi

# ============================================
# 5. Final Service Verification
# ============================================
print_step "Running final service verification..."

ALL_SERVICES_OK=true

# Verify Database Service
if ! check_db_service > /dev/null 2>&1; then
    print_error "Database service verification failed"
    ALL_SERVICES_OK=false
fi

# Verify Database Connection
if ! php artisan db:show >/dev/null 2>&1; then
    print_error "Database connection verification failed"
    ALL_SERVICES_OK=false
fi

# Verify Redis
if ! redis-cli -h $REDIS_HOST -p $REDIS_PORT ping > /dev/null 2>&1; then
    print_error "Redis verification failed"
    ALL_SERVICES_OK=false
fi

# Verify Horizon
if ! pgrep -f "artisan horizon" > /dev/null; then
    print_error "Horizon verification failed"
    ALL_SERVICES_OK=false
fi

# ============================================
# Summary
# ============================================
echo ""
if [ "$ALL_SERVICES_OK" = true ]; then
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║   All Services Running Successfully!  ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}Service Status:${NC}"
    
    # Database service info
    DB_SERVICE=$(check_db_service || echo "unknown")
    echo -e "  ${GREEN}✓${NC} Database Service: Running ($DB_SERVICE)"
    
    # Database connection info
    DB_NAME=$(php artisan tinker --execute="echo config('database.connections.pgsql.database');" 2>/dev/null | grep -v "^>" | head -1 || echo "unknown")
    DB_HOST=$(php artisan tinker --execute="echo config('database.connections.pgsql.host');" 2>/dev/null | grep -v "^>" | head -1 || echo "unknown")
    echo -e "  ${GREEN}✓${NC} Database Connection: Connected to $DB_NAME on $DB_HOST"
    
    echo -e "  ${GREEN}✓${NC} Redis: Running on $REDIS_HOST:$REDIS_PORT"
    
    HORIZON_PID=$(pgrep -f "artisan horizon" || echo "N/A")
    echo -e "  ${GREEN}✓${NC} Horizon: Running (PID: $HORIZON_PID)"
    
    echo ""
    echo -e "${BLUE}Useful Commands:${NC}"
    echo "  - Check Horizon status: php artisan horizon:status"
    echo "  - View Horizon dashboard: http://your-domain/horizon"
    echo "  - Stop Horizon: php artisan horizon:terminate"
    echo "  - View Horizon logs: tail -f storage/logs/horizon.log"
    echo ""
else
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    echo -e "${RED}║   Some Services Failed to Start       ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════╝${NC}"
    echo ""
    exit 1
fi
