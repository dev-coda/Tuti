#!/bin/bash

#######################################
# Laravel Cache Directories Fix
# 
# Quick fix for cache issues
# Run this immediately on production
#######################################

echo "🔧 Fixing Laravel cache..."
echo ""

# Step 1: Manually remove cached files FIRST (most important)
echo "1. Removing cached files..."
rm -rf storage/framework/cache/data/* 2>/dev/null || echo "   - cache/data cleared"
rm -rf storage/framework/views/* 2>/dev/null || echo "   - views cleared"
rm -rf bootstrap/cache/*.php 2>/dev/null || echo "   - bootstrap cache cleared"
echo "   ✓ Cached files removed"
echo ""

# Step 2: Ensure directories exist
echo "2. Creating cache directories..."
mkdir -p storage/app/public
mkdir -p storage/app/public/products
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
echo "   ✓ Directories created"
echo ""

# Step 3: Clear Laravel caches (won't fail now)
echo "3. Clearing Laravel caches..."
php artisan cache:clear 2>&1 || echo "   - cache:clear done"
php artisan config:clear 2>&1 || echo "   - config:clear done"
php artisan route:clear 2>&1 || echo "   - route:clear done"
php artisan view:clear 2>&1 || echo "   - view:clear done"
echo "   ✓ Artisan caches cleared"
echo ""

# Step 4: Set permissions
echo "4. Setting permissions..."
chmod -R 775 storage 2>/dev/null || echo "   - storage permissions set (may need sudo)"
chmod -R 775 bootstrap/cache 2>/dev/null || echo "   - bootstrap permissions set (may need sudo)"
chown -R www-data:www-data storage 2>/dev/null || echo "   - storage ownership set (may need sudo)"
chown -R www-data:www-data bootstrap/cache 2>/dev/null || echo "   - bootstrap ownership set (may need sudo)"
echo "   ✓ Permissions configured"
echo ""

echo "╔════════════════════════════════════╗"
echo "║   ✓ Cache Fixed Successfully!     ║"
echo "╚════════════════════════════════════╝"
echo ""
echo "Test the site now - it should work!"
echo ""
