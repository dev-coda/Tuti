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

# Function to check if PostgreSQL is actually listening on port 5432
check_db_service() {
    # Only check if PostgreSQL is actually listening on port 5432 (most reliable)
    # Don't check for processes - a process might exist but not be listening
    if timeout 2 bash -c "echo > /dev/tcp/127.0.0.1/5432" 2>/dev/null || nc -z 127.0.0.1 5432 2>/dev/null || (command -v pg_isready >/dev/null 2>&1 && timeout 2 pg_isready -h 127.0.0.1 -p 5432 >/dev/null 2>&1); then
        echo "running"
        return 0
    fi
    return 1
}

# Function to find and start the actual PostgreSQL cluster
find_and_start_postgresql() {
    # Method 1: Use pg_lsclusters to find available clusters (most reliable)
    if command -v pg_lsclusters >/dev/null 2>&1; then
        CLUSTERS=$(pg_lsclusters 2>/dev/null | awk 'NR>1 {print $1 " " $2}' || echo "")
        if [ -n "$CLUSTERS" ]; then
            while IFS= read -r line; do
                if [ -n "$line" ]; then
                    VERSION=$(echo "$line" | awk '{print $1}')
                    CLUSTER=$(echo "$line" | awk '{print $2}')
                    STATUS=$(pg_lsclusters 2>/dev/null | grep "$VERSION $CLUSTER" | awk '{print $6}' || echo "")
                    PORT=$(pg_lsclusters 2>/dev/null | grep "$VERSION $CLUSTER" | awk '{print $3}' || echo "5432")
                    
                    # Check if cluster is actually listening, not just marked as "online"
                    PORT_LISTENING=false
                    if [ -n "$PORT" ]; then
                        if timeout 2 bash -c "echo > /dev/tcp/127.0.0.1/$PORT" 2>/dev/null || nc -z 127.0.0.1 "$PORT" 2>/dev/null || timeout 2 pg_isready -h 127.0.0.1 -p "$PORT" >/dev/null 2>&1; then
                            PORT_LISTENING=true
                        fi
                    fi
                    
                    if [ "$STATUS" != "online" ] || [ "$PORT_LISTENING" = false ]; then
                        if [ "$STATUS" = "online" ] && [ "$PORT_LISTENING" = false ]; then
                            print_warning "Cluster $VERSION/$CLUSTER shows as online but is not listening on port $PORT, restarting..."
                        else
                            print_step "Starting PostgreSQL cluster $VERSION/$CLUSTER..."
                        fi
                        if sudo -u postgres pg_ctlcluster "$VERSION" "$CLUSTER" start 2>&1; then
                            sleep 3
                            # Verify it's actually listening
                            if timeout 2 bash -c "echo > /dev/tcp/127.0.0.1/$PORT" 2>/dev/null || nc -z 127.0.0.1 "$PORT" 2>/dev/null || timeout 2 pg_isready -h 127.0.0.1 -p "$PORT" >/dev/null 2>&1; then
                                print_success "PostgreSQL cluster $VERSION/$CLUSTER started and listening on port $PORT"
                                return 0
                            else
                                print_warning "Cluster $VERSION/$CLUSTER start command succeeded but not listening yet"
                            fi
                        fi
                    else
                        print_success "PostgreSQL cluster $VERSION/$CLUSTER is already online and listening"
                        return 0
                    fi
                fi
            done <<< "$CLUSTERS"
        fi
    fi
    
    # Method 2: Try to find and start versioned systemd services
    if command -v systemctl &> /dev/null; then
        # Find all postgresql@ services
        VERSIONED_SERVICES=$(timeout 5 systemctl list-unit-files --type=service --no-pager 2>/dev/null | grep -E '^postgresql@[0-9]+' | awk '{print $1}' | sed 's/\.service$//' || echo "")
        
        if [ -n "$VERSIONED_SERVICES" ]; then
            for service in $VERSIONED_SERVICES; do
                print_step "Attempting to start $service..."
                if timeout 10 sudo systemctl start "$service" 2>&1; then
                    sleep 3
                    if timeout 2 systemctl is-active --quiet "$service" 2>/dev/null; then
                        # Verify it's actually listening
                        sleep 2
                        if timeout 2 pg_isready -h 127.0.0.1 -p 5432 >/dev/null 2>&1 || timeout 2 bash -c "echo > /dev/tcp/127.0.0.1/5432" 2>/dev/null; then
                            print_success "PostgreSQL service started: $service"
                            return 0
                        fi
                    fi
                fi
            done
        fi
    fi
    
    return 1
}

# Check if database service is running (listening on port 5432)
DB_SERVICE_STATUS=$(check_db_service)
if [ -n "$DB_SERVICE_STATUS" ]; then
    print_success "PostgreSQL is listening on port 5432"
else
    print_warning "PostgreSQL is not listening on port 5432!"
    echo ""
    echo "Attempting to start PostgreSQL cluster..."
    
    if find_and_start_postgresql; then
        print_success "PostgreSQL started successfully"
        
        # Wait for PostgreSQL to be ready and verify it's listening
        print_step "Waiting for PostgreSQL to be ready..."
        MAX_PORT_CHECKS=10
        PORT_CHECK_COUNT=0
        PORT_READY=false
        
        while [ $PORT_CHECK_COUNT -lt $MAX_PORT_CHECKS ]; do
            if timeout 2 bash -c "echo > /dev/tcp/127.0.0.1/5432" 2>/dev/null || nc -z 127.0.0.1 5432 2>/dev/null || timeout 2 pg_isready -h 127.0.0.1 -p 5432 >/dev/null 2>&1; then
                PORT_READY=true
                break
            fi
            PORT_CHECK_COUNT=$((PORT_CHECK_COUNT + 1))
            if [ $PORT_CHECK_COUNT -lt $MAX_PORT_CHECKS ]; then
                print_warning "PostgreSQL port not ready yet (attempt $PORT_CHECK_COUNT/$MAX_PORT_CHECKS), waiting..."
                sleep 2
            fi
        done
        
        if [ "$PORT_READY" = true ]; then
            print_success "PostgreSQL is now listening on port 5432"
        else
            print_error "PostgreSQL started but is not listening on port 5432 after $MAX_PORT_CHECKS attempts"
            exit 1
        fi
    else
        print_error "Could not start PostgreSQL automatically"
        echo ""
        echo "Troubleshooting information:"
        echo ""
        
        # Show available clusters
        if command -v pg_lsclusters >/dev/null 2>&1; then
            echo "Available PostgreSQL clusters:"
            pg_lsclusters 2>/dev/null || echo "  (pg_lsclusters not available)"
            echo ""
        fi
        
        # Show available services
        if command -v systemctl &> /dev/null; then
            echo "Available PostgreSQL systemd services:"
            timeout 5 systemctl list-unit-files --type=service --no-pager 2>/dev/null | grep -E 'postgresql|postgres' || echo "  (none found)"
            echo ""
        fi
        
        echo "Please start PostgreSQL manually using one of these methods:"
        echo ""
        if command -v pg_lsclusters >/dev/null 2>&1; then
            echo "  Method 1 (using pg_ctlcluster):"
            echo "    sudo -u postgres pg_ctlcluster <version> <cluster> start"
            echo "    Example: sudo -u postgres pg_ctlcluster 14 main start"
            echo ""
        fi
        echo "  Method 2 (using systemctl):"
        echo "    sudo systemctl start postgresql@<version>-<cluster>"
        echo "    Example: sudo systemctl start postgresql@14-main"
        echo ""
        echo "  To find your cluster: pg_lsclusters"
        echo "  To find services: systemctl list-unit-files | grep postgresql"
        exit 1
    fi
fi

# ============================================
# 2. Verify Database Connection
# ============================================
print_step "Verifying database connection..."

# Test database connection using Laravel (with timeout to avoid hanging)
MAX_RETRIES=5
RETRY_COUNT=0
DB_CONNECTED=false

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if timeout 10 php artisan db:show >/dev/null 2>&1; then
        DB_CONNECTED=true
        break
    fi
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        print_warning "Database connection attempt $RETRY_COUNT failed, retrying..."
        sleep 3
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

# Verify Database Connection (with timeout)
if ! timeout 10 php artisan db:show >/dev/null 2>&1; then
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
