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

echo "Clearing all caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Removing cached files manually..."
rm -rf storage/framework/cache/data/*
rm -rf storage/framework/views/*
rm -rf bootstrap/cache/*.php

echo "Setting permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache

echo ""
echo "✓ Cache completely cleared and fixed!"
echo "The application should work now."
echo "Test by visiting the site."
