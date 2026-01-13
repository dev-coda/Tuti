#!/bin/bash

#######################################
# Laravel Cache Directories Fix
# 
# Quick fix for missing cache directories
# Run this immediately on production
#######################################

set -e

echo "Creating Laravel storage structure..."

# Create all required directories
mkdir -p storage/app/public
mkdir -p storage/app/public/products
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

echo "Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear

echo "✓ Cache directories fixed!"
echo ""
echo "The application should work now."
echo "Test by visiting the site."
