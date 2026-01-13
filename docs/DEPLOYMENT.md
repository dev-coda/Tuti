# Deployment Guide

This document explains how to deploy Tuti to production using the automated deployment script.

## Quick Start

```bash
# On production server, navigate to project directory
cd /path/to/tuti

# Run deployment script (defaults to stage branch)
bash deploy.sh

# Or deploy from a specific branch
bash deploy.sh master
```

## Deployment Script (`deploy.sh`)

The automated deployment script handles all necessary steps:

1. ✅ **Enables maintenance mode** - Prevents user access during deployment
2. ✅ **Pulls latest code** - Updates from specified git branch
3. ✅ **Installs dependencies** - Runs `composer install`
4. ✅ **Runs migrations** - Updates database schema
5. ✅ **Fixes storage symlink** - Critical for images and file uploads
6. ✅ **Sets permissions** - Ensures proper file/directory permissions
7. ✅ **Clears caches** - Removes old cached data
8. ✅ **Rebuilds caches** - Generates fresh optimized caches
9. ✅ **Restarts queue workers** - Ensures background jobs pick up changes
10. ✅ **Restarts web services** - Reloads PHP-FPM and Nginx
11. ✅ **Verifies storage** - Checks symlink and directory structure
12. ✅ **Disables maintenance mode** - Brings application back online

## Initial Setup

### 1. Make the Script Executable

```bash
chmod +x deploy.sh
```

### 2. Configure Environment Variables (Optional)

You can customize the deployment by setting these environment variables:

```bash
# Set PHP version (default: 8.1)
export PHP_VERSION=8.2

# Set web server user (default: www-data)
export WEB_USER=nginx

# Then run deployment
bash deploy.sh
```

### 3. Set Up Sudo Access (for service restarts)

The script needs sudo access to restart services. Add to `/etc/sudoers.d/deploy`:

```
# Replace 'deploy-user' with your actual deployment user
deploy-user ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.1-fpm
deploy-user ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx
deploy-user ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart all
```

## Usage Examples

### Deploy from Stage Branch (Default)
```bash
bash deploy.sh
```

### Deploy from Master Branch
```bash
bash deploy.sh master
```

### Deploy from Custom Branch
```bash
bash deploy.sh feature-branch
```

## Manual Deployment Steps

If you need to deploy manually without the script:

```bash
# 1. Enable maintenance mode
php artisan down --retry=60

# 2. Pull latest code
git pull origin stage

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Run migrations
php artisan migrate --force

# 5. Fix storage symlink (CRITICAL!)
php artisan storage:link --force

# 6. Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 7. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 8. Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# 9. Restart services
sudo supervisorctl restart all
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx

# 10. Disable maintenance mode
php artisan up
```

## Troubleshooting

### Images Not Appearing After Deployment

This is the most common issue. The `public/storage` symlink gets broken during deployment.

**Fix:**
```bash
rm public/storage
php artisan storage:link --force
chmod -R 755 storage/app/public
chown -R www-data:www-data storage/app/public
```

**Verify:**
```bash
ls -la public/storage
# Should show: public/storage -> ../storage/app/public

ls -la storage/app/public/products/
# Should list your product images
```

### Migrations Fail

**Symptom:** Database migration errors during deployment

**Fix:**
```bash
# Check migration status
php artisan migrate:status

# Rollback last migration (if needed)
php artisan migrate:rollback --step=1

# Try running migrations again
php artisan migrate --force
```

### Permissions Denied

**Symptom:** Cannot write to storage or cache directories

**Fix:**
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache
```

### Services Won't Restart

**Symptom:** Script shows warnings about service restart failures

**Fix:**
```bash
# Check if services are running
sudo systemctl status php8.1-fpm
sudo systemctl status nginx

# Manually restart
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

### Queue Workers Not Processing Jobs

**Symptom:** Background jobs not running after deployment

**Fix:**
```bash
# Check supervisor status
sudo supervisorctl status

# Restart all workers
sudo supervisorctl restart all

# Or restart specific worker
sudo supervisorctl restart laravel-worker:*
```

## Post-Deployment Checklist

After running the deployment script, verify:

- [ ] Application loads without errors
- [ ] Product images are displaying correctly
- [ ] Can upload new images in admin
- [ ] Background jobs are processing (check queue)
- [ ] Database migrations completed successfully
- [ ] No errors in logs: `tail -f storage/logs/laravel.log`
- [ ] Test critical features:
  - [ ] User login/registration
  - [ ] Product catalog
  - [ ] Cart functionality
  - [ ] Order placement
  - [ ] Admin dashboard

## Monitoring

After deployment, monitor the application:

```bash
# Watch Laravel logs
tail -f storage/logs/laravel.log

# Watch Nginx error logs
sudo tail -f /var/log/nginx/error.log

# Watch PHP-FPM logs
sudo tail -f /var/log/php8.1-fpm.log

# Check queue worker status
sudo supervisorctl status
```

## Rollback

If deployment fails and you need to rollback:

```bash
# 1. Go back to previous commit
git log --oneline  # Find the commit hash
git reset --hard <previous-commit-hash>

# 2. Rollback database (if migrations were run)
php artisan migrate:rollback --step=1

# 3. Clear caches
php artisan config:clear
php artisan cache:clear

# 4. Rebuild caches
php artisan config:cache
php artisan route:cache

# 5. Restart services
sudo systemctl restart php8.1-fpm nginx
sudo supervisorctl restart all
```

## CI/CD Integration

### GitHub Actions Example

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [ stage ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /path/to/tuti
            bash deploy.sh stage
```

## Security Notes

1. **Never commit `.env` file** - Contains sensitive credentials
2. **Use environment-specific `.env` files** - Different for local/staging/production
3. **Keep dependencies updated** - Run `composer update` regularly
4. **Monitor vulnerability alerts** - Check GitHub security advisories
5. **Restrict sudo access** - Only allow necessary commands in sudoers
6. **Use SSH keys** - Never use password authentication for deployment

## Support

If you encounter issues during deployment:

1. Check the logs: `storage/logs/laravel.log`
2. Verify all steps completed in the script output
3. Review this troubleshooting guide
4. Contact the development team with:
   - Deployment script output
   - Error messages from logs
   - Git commit hash being deployed
   - Server environment details
