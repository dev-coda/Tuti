#!/bin/bash

# Deploy Audit Report Fix
# Run this on production server after git pull

echo "🚀 Deploying Audit Report Fix..."
echo ""

# Pull latest changes
echo "1️⃣ Pulling latest code..."
git pull origin master

# Clear all caches
echo ""
echo "2️⃣ Clearing application cache..."
php artisan cache:clear

echo ""
echo "3️⃣ Clearing route cache..."
php artisan route:clear

echo ""
echo "4️⃣ Clearing config cache..."
php artisan config:clear

echo ""
echo "5️⃣ Clearing view cache..."
php artisan view:clear

# Rebuild caches for production
echo ""
echo "6️⃣ Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify the route exists
echo ""
echo "7️⃣ Verifying audit route..."
php artisan route:list --path=orderaudit

echo ""
echo "✅ Deployment complete!"
echo ""
echo "Test the route at: https://tuti.com.co/admin/orderauditexport?from_date=2026-01-21"
echo ""
