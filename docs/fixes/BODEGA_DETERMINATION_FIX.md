# Bodega Determination Fix - Production Debugging

## Problem
When inventory management is enabled in production, every order errors out with:
```
"No se pudo determinar la bodega para su zona"
```

## Root Causes Identified

1. **Zone Code Field Mismatch**: The code was only checking `zone->code` but not falling back to `zone->zone` field initially
2. **Missing Logging**: No detailed logging to debug why bodega determination fails
3. **Config Cache Issues**: Production might have stale config cache

## Fixes Applied

### 1. Fixed Zone Code Determination (`CartController.php`)
- **Before**: Only checked `$zone->code`, returned null if not set
- **After**: Checks both `$zone->code` and `$zone->zone` fields (fallback)
- **Location**: Line 531 in `processOrder()` method

```php
// OLD (line 531):
$zoneCode = $zone?->code ?? null;

// NEW:
$zoneCode = $zone?->code ?? $zone?->zone ?? null;
```

### 2. Enhanced Logging (`CartController.php`)
Added comprehensive logging when bodega determination fails:
- User information (ID, email)
- Zone information (ID, code, zone field)
- All available mappings (DB and config)
- Fallback zone attempts

### 3. Enhanced ZoneWarehouse Model (`ZoneWarehouse.php`)
Added debug logging at each step of bodega lookup:
- DB exact match attempts
- DB case-insensitive match attempts
- Config file exact match attempts
- Config file case-insensitive match attempts
- Final failure with available mappings

### 4. Improved Diagnostic Command (`DiagnoseInventory.php`)
- Now uses `ZoneWarehouse::getBodegaForZone()` (same as production)
- Shows both DB and config mappings
- Provides clear solutions

## How to Debug in Production

### Step 1: Run Diagnostic Command
```bash
php artisan inventory:diagnose 123456789
```
Replace `123456789` with the client's document number.

This will show:
- User's zones and zone codes
- Whether bodega mapping exists
- Available mappings in DB and config

### Step 2: Check Logs
Look for these log entries in `storage/logs/laravel.log`:

**When bodega determination fails:**
```
[WARNING] Bodega determination failed
[ERROR] Bodega determination failed - no mapping found
[WARNING] ZoneWarehouse::getBodegaForZone - No mapping found
```

**When successful:**
```
[DEBUG] ZoneWarehouse::getBodegaForZone - Found in DB (exact match)
[DEBUG] ZoneWarehouse::getBodegaForZone - Found in config (exact match)
```

### Step 3: Verify Zone-Warehouse Mappings

**Check Database:**
```sql
SELECT * FROM zone_warehouses ORDER BY zone_code;
```

**Check Config File:**
```bash
php artisan tinker
>>> config('zone_warehouses.mappings');
```

**Clear Config Cache (if needed):**
```bash
php artisan config:clear
php artisan config:cache
```

### Step 4: Common Issues and Solutions

#### Issue: Zone code is NULL
**Symptoms**: Logs show `zone_code: NULL`
**Solution**: 
- Ensure zones have either `code` or `zone` field populated
- Check user's zone assignment

#### Issue: Zone code doesn't match mappings
**Symptoms**: Logs show zone code exists but no mapping found
**Solution**:
- Check for case sensitivity issues (mappings are case-insensitive now)
- Check for whitespace issues (auto-trimmed now)
- Add missing mapping to DB or config

#### Issue: Config file not loaded
**Symptoms**: Config mappings show empty in logs
**Solution**:
```bash
php artisan config:clear
php artisan config:cache
# Verify config file exists: config/zone_warehouses.php
```

#### Issue: Zone has `zone` field but not `code` field
**Symptoms**: Zone exists but `code` is NULL, `zone` has value
**Solution**: 
- Fixed! Code now checks both fields
- Ensure zones are properly synced from SOAP service

## Testing the Fix

### Test Case 1: Zone with `code` field
```php
// Zone has code = "102"
// Should find bodega "MDTAT" from config
```

### Test Case 2: Zone with only `zone` field
```php
// Zone has code = NULL, zone = "102"
// Should now find bodega "MDTAT" (previously failed)
```

### Test Case 3: Case-insensitive matching
```php
// Zone code = "102" or "102 " or " 102"
// Should match config key "102"
```

## Files Modified

1. `app/Http/Controllers/CartController.php`
   - Fixed zone code determination (line 531)
   - Added comprehensive error logging (lines 533-563)

2. `app/Models/ZoneWarehouse.php`
   - Added debug logging throughout `getBodegaForZone()` method

3. `app/Console/Commands/DiagnoseInventory.php`
   - Enhanced to use same method as production
   - Shows both DB and config mappings

## Deployment Checklist

- [ ] Deploy code changes
- [ ] Clear config cache: `php artisan config:clear && php artisan config:cache`
- [ ] Verify `config/zone_warehouses.php` exists and has mappings
- [ ] Check `zone_warehouses` table has required mappings
- [ ] Test with a real user order
- [ ] Monitor logs for any remaining issues
- [ ] Run diagnostic command for affected users: `php artisan inventory:diagnose DOCUMENT_NUMBER`

## Monitoring

After deployment, monitor logs for:
- `[WARNING] Bodega determination failed` - Should decrease significantly
- `[ERROR] Bodega determination failed - no mapping found` - Should be rare
- `[DEBUG] ZoneWarehouse::getBodegaForZone - Found` - Should increase

If errors persist, use the diagnostic command to identify specific users/zones with issues.

