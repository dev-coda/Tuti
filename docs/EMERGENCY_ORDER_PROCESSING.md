# Emergency Order Processing System

## Overview
This document describes the emergency order processing features available in the admin settings panel. These features were created to handle urgent situations where orders need to be processed immediately or with overridden delivery dates.

## Features

### 1. Forzar Fecha de Entrega (Force Delivery Date)

#### Location
Admin Panel → Configuraciones → Gestión de Emergencia de Pedidos

#### Purpose
When activated, this setting overrides the scheduled delivery date for ALL orders sent to the SOAP API, forcing them to use the next business day instead.

#### How It Works
- **When Disabled (Default)**: Orders use their normal scheduled delivery dates and may be held with `STATUS_WAITING` if created before the seller's visit day
- **When Enabled**: 
  - ALL new orders bypass the waiting/delay mechanism and are processed immediately
  - ALL orders transmitted to the SOAP API have their delivery date overridden to the next available business day
  - Existing waiting orders will be processed immediately when the toggle is active

#### Important Notes
- ⚠️ **This affects ALL orders** being processed while the setting is active
- The override happens at the SOAP transmission level in `OrderRepository::sendData()`
- Original delivery dates are preserved in the database; only the SOAP payload is affected
- The system logs all delivery date overrides with details
- A warning banner is displayed in the admin panel when this setting is active

#### Use Cases
- When there are delays in order processing and all pending orders need to be expedited
- Emergency situations requiring immediate order fulfillment
- When the normal delivery calendar needs to be temporarily bypassed

#### Technical Details
- **Setting Key**: `force_delivery_date_enabled`
- **Default Value**: `0` (disabled)
- **Bypass Locations**: 
  - `app/Http/Controllers/CartController.php` (line ~833): Prevents new orders from being set to `STATUS_WAITING`
  - `app/Jobs/ProcessOrderAsync.php` (line ~89): Processes waiting orders immediately instead of releasing them
  - `app/Repositories/OrderRepository.php` (line ~88): Overrides delivery date in SOAP payload
- **Log Channel**: `soap` for delivery date overrides, `default` for bypass actions

### 2. Despachar Día (Process Daily Orders)

#### Location
Admin Panel → Configuraciones → Gestión de Emergencia de Pedidos

#### Purpose
This button immediately processes ALL waiting orders that were created in the last 24 hours, regardless of their scheduled transmission date.

#### How It Works
1. Finds all orders with status `STATUS_WAITING` created in the last 24 hours
2. Changes their status from `STATUS_WAITING` to `STATUS_PENDING`
3. Dispatches `ProcessOrderAsync` jobs to the `orders` queue for each order
4. Orders are then processed asynchronously through the normal order processing pipeline

#### Important Notes
- ⚠️ **Requires user confirmation** before executing
- Only affects orders with `STATUS_WAITING` status
- Orders must have been created within the last 24 hours
- Orders are dispatched to the queue and processed asynchronously
- The system logs the action with details about how many orders were processed

#### Use Cases
- When orders are held due to system issues and need to be released immediately
- Emergency situations where all recent pending orders need to be transmitted
- When scheduled transmission dates need to be bypassed for a batch of orders

#### Technical Details
- **Controller Method**: `SettingController::processWaitingOrders()`
- **Status Change**: `STATUS_WAITING` → `STATUS_PENDING`
- **Time Window**: Last 24 hours (configurable in code)
- **Queue**: `orders`
- **Job**: `ProcessOrderAsync`

## Combined Usage

When both features are used together:
1. Enable "Forzar Fecha de Entrega" to override delivery dates
2. Click "Despachar Día" to process all waiting orders
3. All orders will be processed with the next business day as their delivery date

This combination is useful for critical situations where:
- Orders are backed up and need immediate processing
- Normal delivery schedules cannot be met
- Emergency fulfillment is required

## Safety Features

1. **Confirmation Dialog**: "Despachar Día" requires explicit user confirmation
2. **Detailed Logging**: All actions are logged with user information and timestamps
3. **Visual Warnings**: Red border and warning icons indicate emergency nature
4. **Status Indicators**: Clear messages show when "Forzar Fecha de Entrega" is active
5. **Audit Trail**: All setting changes and order processing actions are logged

## Logging

### Force Delivery Date
```
Channel: soap (storage/logs/soap.log)
Message: "Force Delivery Date is ACTIVE - Overriding delivery date"
Data: order_id, original_delivery_date, forced_delivery_date, setting_enabled_by
```

### Process Waiting Orders
```
Channel: default (storage/logs/laravel.log)
Level: INFO
Message: "Emergency order processing initiated"
Data: total_orders, user, timestamp, force_delivery_date_enabled
```

### Setting Changes
```
Channel: default (storage/logs/laravel.log)
Level: WARNING
Message: "Force Delivery Date setting changed"
Data: enabled, user, timestamp
```

## Database Structure

### Settings Table Entries

```php
// Force Delivery Date Setting
[
    'key' => 'force_delivery_date_enabled',
    'name' => 'Forzar Fecha de Entrega',
    'value' => '0', // '1' = enabled, '0' = disabled
    'show' => false
]
```

## API Integration

The delivery date override happens in the SOAP XML payload:

```xml
<dyn:deliveryDate>YYYY-MM-DD</dyn:deliveryDate>
```

When "Forzar Fecha de Entrega" is enabled:
- Original: Uses `$order->delivery_date` from the database
- Override: Uses `self::getBusinessDay(0)` (next business day)

## Routes

```php
// Force Delivery Date Toggle
POST /settings/update-force-delivery-date
Controller: SettingController::updateForceDeliveryDate()

// Process Waiting Orders
POST /settings/process-waiting-orders
Controller: SettingController::processWaitingOrders()
```

## Frontend Components

### Force Delivery Date Toggle
- Type: Checkbox with auto-submit
- Location: `resources/views/settings/index.blade.php` (lines 113-136)
- Colors: Red theme to indicate emergency nature
- Status: Shows active warning when enabled

### Despachar Día Button
- Type: Button with confirmation dialog
- Location: `resources/views/settings/index.blade.php` (lines 139-149)
- Colors: Red theme with lightning icon
- Confirmation: JavaScript `confirm()` dialog

## Best Practices

1. **Use Sparingly**: These are emergency features and should not be used for regular operations
2. **Monitor Logs**: Always check logs after using these features to ensure proper execution
3. **Disable After Use**: Turn off "Forzar Fecha de Entrega" as soon as the emergency is resolved
4. **Document Usage**: Keep internal records of when and why these features were used
5. **Check Queue Status**: Ensure Horizon/Queue workers are running before using "Despachar Día"

## Troubleshooting

### "Despachar Día" shows "No orders found"
- Check if there are any orders with `STATUS_WAITING` in the last 24 hours
- Verify the time window (currently 24 hours)
- Check database: `SELECT * FROM orders WHERE status = 'waiting' AND created_at >= NOW() - INTERVAL 24 HOUR`

### Orders not processing after "Despachar Día"
- Verify queue workers are running: `php artisan horizon:status` or `php artisan queue:work`
- Check `jobs` table for pending jobs
- Review logs in `storage/logs/laravel.log`

### "Forzar Fecha de Entrega" not working
- Check setting in database: `SELECT * FROM settings WHERE key = 'force_delivery_date_enabled'`
- Review SOAP logs: `storage/logs/soap.log`
- Verify that orders are being transmitted (not just created)

### Orders using wrong delivery date
- If "Forzar Fecha de Entrega" is active, delivery dates will be overridden
- Check if holidays are properly configured (affects business day calculation)
- Review `getBusinessDay()` logic in `OrderRepository.php`

## Related Documentation

- [Order Retry System](ORDER_RETRY_SYSTEM.md)
- [Holidays System](HOLIDAYS_SYSTEM.md)
- [Queue Setup](README-QUEUE.md)
- [Deployment Guide](DEPLOYMENT.md)

## Change Log

### 2026-01-13
- Initial implementation of both features
- Added comprehensive logging
- Created admin UI with red theme for emergency indication
- Implemented safety confirmations

---

**⚠️ IMPORTANT**: These features are designed for emergency use only. Misuse can lead to incorrect order fulfillment dates and customer service issues. Always verify the impact before activating these features.
