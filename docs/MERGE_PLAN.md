# Merge Plan: Stage → Master

## Features to Merge

### 1. Monthly Report Downloader + ShouldQueue Fix
**Commits:**
- `280d095` - 30-oct (contains OrdersMonthlyExport.php creation, ExportFile model, migrations, documentation)
- `e147a7b` - fix report downloader (fixes the ShouldQueue interface issue)

**Files affected:**
- `app/Exports/OrdersMonthlyExport.php` (NEW)
- `app/Models/ExportFile.php` (NEW)
- `app/Http/Controllers/Admin/OrderController.php` (MODIFIED - adds exportMonthly method)
- `database/migrations/2025_10_30_000002_create_export_files_table.php` (NEW)
- `docs/MONTHLY_EXPORTS_SYSTEM.md` (NEW)
- `docs/MONTHLY_EXPORT_QUICK_START.md` (NEW)
- `resources/views/orders/index.blade.php` (MODIFIED - adds monthly export UI)
- `routes/admin.php` (MODIFIED - adds export routes)

### 2. Multiple Images Uploader for Products
**Commits:**
- `017338a` - Add multiple image upload functionality for products

**Files affected:**
- `app/Http/Controllers/Admin/ProductController.php` (MODIFIED - adds multi-image upload)
- `resources/views/products/create.blade.php` (MODIFIED - multi-file input)
- `resources/views/products/edit.blade.php` (MODIFIED - multi-file input)
- `resources/js/components/ProductImageReorder.vue` (image reordering component)
- `database/migrations/2025_08_08_000001_add_position_to_product_images_table.php` (if exists)

### 3. Content Pages Module
**Commits:**
- `280d095` - 30-oct (contains all content pages functionality)

**Files affected:**
- `app/Http/Controllers/Admin/ContentPageController.php` (NEW)
- `app/Http/Controllers/ContentController.php` (MODIFIED)
- `app/Models/ContentPage.php` (NEW)
- `database/migrations/2025_10_30_000001_create_content_pages_table.php` (NEW)
- `resources/views/content-pages/create.blade.php` (NEW)
- `resources/views/content-pages/edit.blade.php` (NEW)
- `resources/views/content-pages/index.blade.php` (NEW)
- `resources/views/content/page.blade.php` (NEW)
- `resources/views/elements/admin/aside.blade.php` (MODIFIED - adds menu)
- `docs/CONTENT_PAGES_FEATURE.md` (NEW)
- `routes/admin.php` (MODIFIED - adds content routes)
- `routes/web.php` (MODIFIED - adds public content routes)

### 4. Holidays System Updates
**Commits:**
- `280d095` - 30-oct (contains recent holiday documentation and controller changes)
- `b2ed48a` - Update product form fields and holiday controller changes (if needed)
- `3243da1` - feat: Add comprehensive holidays management system (ALREADY IN MASTER - check first)

**Files affected:**
- `app/Http/Controllers/Admin/HolidayController.php` (MODIFIED)
- `resources/views/holidays/index.blade.php` (MODIFIED)
- `docs/HOLIDAYS_SYSTEM.md` (NEW)

### 5. Coupon Fix (Bonus - if needed)
**Commits:**
- `1da9771` - coupon fix (improves error handling and logging)

**Files affected:**
- `app/Http/Controllers/CartController.php` (MODIFIED)
- `app/Services/CouponService.php` (MODIFIED)

## Merge Strategy

### Option 1: Cherry-Pick Specific Commits (RECOMMENDED)
This approach gives you the most control and avoids merging unwanted changes.

```bash
# 1. Switch to master and update
git checkout master
git pull origin master

# 2. Cherry-pick the main feature commit (this includes monthly exports, content pages, holidays docs)
git cherry-pick 280d095

# 3. Cherry-pick the ShouldQueue fix
git cherry-pick e147a7b

# 4. Cherry-pick multiple images uploader
git cherry-pick 017338a

# 5. Cherry-pick coupon fix (optional but recommended)
git cherry-pick 1da9771

# 6. Resolve any conflicts if they arise

# 7. Test the application

# 8. Push to master
git push origin master
```

### Option 2: Interactive Rebase (Advanced)
If you want more control over which specific files to include:

```bash
git checkout master
git checkout -b feature-merge
git rebase -i master..stage
# Mark commits you want with 'pick', others with 'drop'
```

## Important Notes

1. **Check Dependencies:** The content pages feature might depend on Quill.js or other JS libraries. Make sure those are in master.

2. **Database Migrations:** After merging, run migrations on any environment:
   ```bash
   php artisan migrate
   ```

3. **Clear Caches:** After merge:
   ```bash
   php artisan optimize:clear
   npm run build  # if needed
   ```

4. **Test Each Feature:**
   - Test monthly export download
   - Test uploading multiple product images
   - Test creating/editing content pages
   - Test holidays management
   - Test order creation with coupons

5. **Potential Conflicts:** Watch for conflicts in:
   - `routes/admin.php` (multiple features add routes)
   - `resources/views/elements/admin/aside.blade.php` (menu items)
   - `app/Http/Controllers/Admin/OrderController.php`

## Rollback Plan

If something goes wrong:
```bash
git reset --hard HEAD~N  # where N is number of commits to undo
# OR
git revert <commit-hash>  # to undo a specific commit
```

