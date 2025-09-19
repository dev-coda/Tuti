# ⚠️ Dangerous Migrations Warning

## Overview

This document tracks migrations that have caused or could cause data loss, to prevent future incidents.

## 🚨 Data Loss Incident - September 2025

### What Happened

Migration `2025_09_15_201236_update_cities_list_for_registration.php` caused **massive data loss** by using `City::truncate()` which:

1. **Deleted ALL cities** from the database
2. **Broke foreign key relationships** with users, orders, and contacts
3. **Forced emergency data cleanup** to fix broken references
4. **Result: All users, orders, and contacts were lost**

### Root Cause

```php
// ❌ DANGEROUS - This line caused the data loss
City::truncate();
```

The migration didn't consider that:

-   Cities have foreign key relationships with users, orders, contacts
-   Truncating cities leaves orphaned records with invalid foreign keys
-   This breaks application functionality and data integrity

### Solution Implemented

Created safe migration: `2025_09_19_003533_fix_cities_safely_without_data_loss.php`

**Safe Approach:**

-   ✅ Added `active` and `is_preferred` columns to cities
-   ✅ Keeps existing cities intact (preserves foreign key relationships)
-   ✅ Marks new cities as "preferred" for registration forms
-   ✅ Application shows only preferred cities but preserves all data

## 🛡️ Prevention Guidelines

### Before Creating Migrations

1. **Check for foreign key relationships**

    ```bash
    # Search for foreign keys to the table you're modifying
    grep -r "constrained('table_name')" database/migrations/
    ```

2. **Never use destructive operations on tables with relationships**

    ```php
    // ❌ NEVER do this if table has foreign keys
    TableName::truncate();
    TableName::delete();
    Schema::drop('table_name');
    ```

3. **Use safe alternatives**

    ```php
    // ✅ Safe: Add status columns instead of deleting
    $table->boolean('active')->default(true);
    $table->boolean('is_preferred')->default(false);

    // ✅ Safe: Mark as inactive instead of deleting
    TableName::query()->update(['active' => false]);
    ```

### Safe Migration Patterns

#### Updating Reference Data (Cities, Categories, etc.)

```php
// ✅ SAFE APPROACH
public function up(): void
{
    // Add status columns
    Schema::table('cities', function (Blueprint $table) {
        $table->boolean('active')->default(true);
        $table->boolean('is_preferred')->default(false);
    });

    // Mark existing as non-preferred
    City::query()->update(['is_preferred' => false]);

    // Add new preferred items
    foreach ($newItems as $item) {
        City::firstOrCreate(['name' => $item['name']], $item);
    }
}
```

#### When You Must Remove Data

```php
// ✅ SAFER APPROACH - Check for relationships first
public function up(): void
{
    // Check if data is referenced
    $referencedIds = User::distinct()->pluck('city_id');

    // Only delete unreferenced records
    City::whereNotIn('id', $referencedIds)->delete();

    // Or better: mark as inactive
    City::whereNotIn('id', $referencedIds)->update(['active' => false]);
}
```

## 🧪 Testing Migrations

### Before Running in Production

1. **Test on copy of production data**
2. **Check for broken relationships after migration**
3. **Verify application still works with new data structure**
4. **Have rollback plan ready**

### Commands to Check Data Integrity

```bash
# Check for orphaned foreign keys
php artisan tinker --execute "User::whereNotNull('city_id')->whereNotExists(function(\$q) { \$q->select(DB::raw(1))->from('cities')->whereRaw('cities.id = users.city_id'); })->count();"

# Count records before/after migration
php artisan tinker --execute "echo 'Users: ' . User::count() . ', Orders: ' . Order::count();"
```

## 📋 Migration Checklist

Before running any migration in production:

-   [ ] Does this migration modify a table with foreign key relationships?
-   [ ] Does this migration delete or truncate data?
-   [ ] Have I tested this on a copy of production data?
-   [ ] Do I have a backup of the database?
-   [ ] Do I have a rollback plan?
-   [ ] Have I checked for orphaned records after the migration?
-   [ ] Does the application still work correctly after the migration?

## 🔄 Recovery Procedures

If a migration causes data loss:

1. **Stop the application immediately**
2. **Restore from the most recent backup**
3. **Analyze what went wrong**
4. **Create a safer migration**
5. **Test thoroughly before re-deploying**

## 📚 Related Resources

-   [Laravel Migration Best Practices](https://laravel.com/docs/migrations)
-   [Database Foreign Key Constraints](https://laravel.com/docs/migrations#foreign-key-constraints)
-   [Safe Database Refactoring Patterns](https://www.martinfowler.com/articles/evodb.html)
