# Rutero Sync Fix - Handling Zone Mismatch

## Problem
When inventory management is enabled, orders sometimes fail with "rutero not found" errors. This happens when:
1. User inputs a document and zone
2. The zone no longer matches in the external service (data was updated)
3. System tries to fetch rutero with outdated zone → returns null → error

Additionally, rutero data should be synced and saved in each order to ensure we're sending current data to the external service.

## Solution

### 1. Automatic Retry Without Zone (`UserRepository.php`)

**Problem**: When `getCustomRuteroId($document, $zone)` is called with a zone that no longer matches, it returns null.

**Solution**: Automatically retry without zone parameter if initial call with zone fails.

```php
// Before: Only tried with zone, failed if zone didn't match
$data = UserRepository::getCustomRuteroId($document, $zone);

// After: Automatically retries without zone if zone doesn't match
// This ensures we get fresh data even if zone changed
```

**Implementation**:
- Split `getCustomRuteroId()` into public method and private `fetchRuteroData()` helper
- Public method tries with zone first, then retries without zone if needed
- Logs retry attempts for debugging

### 2. Rutero Sync Before Order Processing (`CartController.php`)

**Problem**: Orders were processed with potentially stale rutero/zone data.

**Solution**: Always sync rutero data before processing orders to ensure current data.

**Implementation**:
- Added `syncUserRuteroData()` method to `UserRepository`
- Calls sync before order processing in `CartController::processOrder()`
- Updates all zones with fresh data from external service
- Updates user name if available
- Handles errors gracefully (continues with existing data if sync fails)

### 3. Zone Validation After Sync (`CartController.php`)

**Problem**: After syncing, the selected zone might no longer be valid.

**Solution**: Re-validate zone_id after sync and update if needed.

**Implementation**:
- After rutero sync, verify zone_id still exists and belongs to user
- If invalid, select first available zone from synced zones
- Update session with valid zone_id
- Use validated zone_id when creating order

### 4. Seller Controller Updates (`SellerController.php`)

**Problem**: Seller controller showed "rutero not found" when zone didn't match.

**Solution**: Use new `syncUserRuteroData()` method which handles zone mismatch automatically.

**Implementation**:
- Replaced manual zone update logic with `syncUserRuteroData()`
- Automatically handles zone mismatch by retrying without zone
- Logs warnings if sync fails but continues with existing data

## Key Changes

### `app/Repositories/UserRepository.php`

1. **Refactored `getCustomRuteroId()`**:
   - Now automatically retries without zone if zone doesn't match
   - Split into public method and private `fetchRuteroData()` helper
   - Added logging for retry attempts

2. **New `syncUserRuteroData()` method**:
   - Syncs rutero data for a user
   - Updates existing zones or creates new ones
   - Updates user name if available
   - Returns success/failure status
   - Handles errors gracefully

### `app/Http/Controllers/CartController.php`

1. **Added rutero sync before order processing**:
   - Syncs rutero data for acting user (client if seller)
   - Reloads zones after sync
   - Handles sync failures gracefully

2. **Zone validation after sync**:
   - Validates zone_id still exists and belongs to user
   - Updates zone_id if invalid
   - Updates session with valid zone_id

3. **Order creation uses synced zone_id**:
   - Uses validated `$zoneId` instead of `$request->zone_id`
   - Ensures order has current zone data

### `app/Http/Controllers/Admin/SellerController.php`

1. **Updated to use `syncUserRuteroData()`**:
   - Automatically handles zone mismatch
   - Logs warnings if sync fails
   - Continues with existing data if sync fails

## Benefits

1. **No More "Rutero Not Found" Errors**: 
   - Automatically retries without zone if zone doesn't match
   - Always gets fresh data from external service

2. **Current Data in Orders**:
   - Rutero data is synced before each order
   - Orders contain current zone/rutero information
   - External service receives up-to-date data

3. **Better Error Handling**:
   - Graceful fallback if sync fails
   - Detailed logging for debugging
   - Continues with existing data if external service unavailable

4. **Automatic Zone Updates**:
   - Zones are automatically updated with fresh data
   - Invalid zones are replaced with valid ones
   - User always has current zone information

## Testing

### Test Case 1: Zone Mismatch
```
1. User has zone "102" in database
2. External service updated zone to "103"
3. User tries to place order with zone "102"
4. Expected: System retries without zone, gets fresh data, updates zones, uses new zone
```

### Test Case 2: Rutero Sync Before Order
```
1. User has stale zone data
2. User places order
3. Expected: System syncs rutero data first, then processes order with current data
```

### Test Case 3: Zone No Longer Exists
```
1. User's zone was deleted in external service
2. User tries to place order
3. Expected: System syncs, gets new zones, uses first available zone
```

## Monitoring

After deployment, monitor logs for:
- `[INFO] Syncing rutero data before order processing` - Should appear before each order
- `[INFO] Rutero data synced successfully` - Should appear after successful sync
- `[INFO] Rutero not found with zone, retrying without zone` - Should appear when zone mismatch occurs
- `[WARNING] Failed to sync rutero data` - Should be rare, indicates external service issues

## Deployment Checklist

- [ ] Deploy code changes
- [ ] Test order processing with zone mismatch scenario
- [ ] Verify rutero sync happens before orders
- [ ] Check logs for sync activity
- [ ] Monitor for any "rutero not found" errors (should be eliminated)
- [ ] Verify orders contain current zone/rutero data

## Rollback Plan

If issues occur:
1. The sync failures are handled gracefully (continues with existing data)
2. Can disable sync by commenting out sync call in `CartController::processOrder()`
3. Original `getCustomRuteroId()` behavior is preserved (just adds retry logic)


