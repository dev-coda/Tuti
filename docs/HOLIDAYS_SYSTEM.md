# Holidays System (Festivos y Sábados) - Documentation

## Overview

The Holidays system manages business days for order delivery calculations, ensuring orders aren't scheduled for delivery on non-working days.

## Purpose

### What Holidays Are Used For

The holidays system is critical for **calculating delivery dates** for customer orders. It works in conjunction with the order processing system to:

1. **Determine Business Days**: Calculate which days are valid for order delivery
2. **Skip Non-Working Days**: Automatically skip holidays when scheduling deliveries
3. **Include Working Saturdays**: Allow deliveries on specific Saturdays that are marked as working days

### How It Works

Located in `app/Repositories/OrderRepository.php`:

```php
public static function isBussinessDay($date)
{
    // Check if it's a holiday (Type 1) - NOT a business day
    $holiday = Holiday::whereDate('date', $date)
                      ->whereTypeId(Holiday::HOLIDAY)
                      ->exists();
    if ($holiday) {
        return false;
    }

    // Check if it's a working Saturday (Type 2) - IS a business day
    $saturday = Holiday::whereDate('date', $date)
                       ->whereTypeId(Holiday::SATURDAY)
                       ->exists();
    if ($saturday) {
        return true;
    }

    // If it's a regular weekday (Mon-Fri) - IS a business day
    if ($date->isWeekday()) {
        return true;
    }

    return false;
}
```

### When Orders Are Scheduled

The `getBusinessDay()` method uses holidays to:

1. Check the current time against the closing time (from settings)
2. If after closing time, start from the next day
3. Skip holidays (Type 1)
4. Skip regular Sundays
5. Include working Saturdays (Type 2)
6. Return the next available delivery date

## Holiday Types

### Type 1: Festivo (Holiday)

-   **Purpose**: National holidays, special non-working days
-   **Effect**: Orders will NOT be delivered on these days
-   **Examples**:
    -   New Year's Day
    -   Independence Day
    -   Christmas
    -   Any company-specific holidays

### Type 2: Sábado (Working Saturday)

-   **Purpose**: Saturdays when delivery IS available
-   **Effect**: Orders CAN be delivered on these specific Saturdays
-   **Use Case**:
    -   Normally Saturdays might not have deliveries
    -   By adding specific Saturdays as Type 2, you enable deliveries on those days
    -   Useful for busy periods or special delivery windows

## Database Structure

**Table**: `holidays`

```
id            - Primary key
name          - Holiday name/description (optional)
type_id       - Type: 1 = Holiday, 2 = Working Saturday
date          - The date (YYYY-MM-DD)
created_at    - Timestamp
updated_at    - Timestamp
```

## Admin Interface

### Location

Admin Panel → Configuraciones → Festivos y Sabados

Or directly at: `/holidays`

### Features

#### View/Filter Holidays

-   **Show All Types**: Filter by "Todos", "Festivo", or "Sábado"
-   **Show Past Holidays**: Checkbox (checked by default) to include past holidays
-   **List View**: See all holidays with date and day of week

#### Add New Holiday

1. Click "➕ Nueva fecha"
2. Select Type:
    - **Festivo**: For holidays (no deliveries)
    - **Sábado**: For working Saturdays (deliveries allowed)
3. Select Date
4. Click "Crear"

**Validation**:

-   If you select "Sábado" type, the date MUST be a Saturday
-   System validates this automatically

#### Import/Export

-   **📤 Importar CSV**: Bulk import holidays from CSV file
-   **📥 Exportar CSV**: Export all holidays to CSV
-   **🔍 Debug Datos**: View detailed holiday statistics

#### Edit/Delete

-   Click on any holiday to edit
-   Use "Eliminar" button to delete

## Recent Changes (October 2025)

### Default Behavior Update

**Problem**:

-   Holidays list only showed future holidays by default
-   Users had to check "Mostrar festivos pasados" to see all holidays
-   This made it difficult to review and manage existing holidays

**Solution**:
Changed default behavior to show ALL holidays (past and future) by default.

#### What Changed

**1. Controller (`HolidayController.php`)**

```php
// OLD: Default to showing only future holidays
->when($request->has('show_past') && $request->show_past, function ($query) {
    return $query; // Show all
}, function ($query) {
    return $query->where('date', '>=', now()); // Default: future only
})

// NEW: Default to showing all holidays
$showPast = $request->has('show_past') ? (bool)$request->show_past : true;

->when(!$showPast, function ($query) {
    // Only filter to future if explicitly unchecked
    return $query->where('date', '>=', now());
})
```

**2. View (`holidays/index.blade.php`)**

-   Added hidden input to ensure value is always sent
-   Checkbox now checked by default
-   When unchecked, explicitly sends `show_past=0`

#### User Experience

**Before**:

-   Open holidays page → See only future holidays
-   Must check "Mostrar festivos pasados" to see all

**After**:

-   Open holidays page → See ALL holidays (past and future)
-   Checkbox is checked by default
-   Uncheck to see only future holidays

## Usage Examples

### Example 1: Adding Colombian Holidays for 2026

```
Type: Festivo
Date: 2026-01-01 (New Year)

Type: Festivo
Date: 2026-01-12 (Three Kings' Day - observed)

Type: Festivo
Date: 2026-03-23 (St. Joseph's Day - observed)

Type: Festivo
Date: 2026-04-09 (Maundy Thursday)

Type: Festivo
Date: 2026-04-10 (Good Friday)
```

### Example 2: Adding Working Saturdays

During December holiday season, enable Saturday deliveries:

```
Type: Sábado
Date: 2025-12-06 (Working Saturday)

Type: Sábado
Date: 2025-12-13 (Working Saturday)

Type: Sábado
Date: 2025-12-20 (Working Saturday)
```

### Example 3: CSV Import

Create a CSV file with this format:

```csv
type_id,date,name
1,2026-01-01,Año Nuevo
1,2026-01-12,Día de Reyes
2,2026-12-05,Sábado laborable
```

Then:

1. Go to Holidays page
2. Click "📤 Importar CSV"
3. Upload your file
4. Review import results

## Impact on Orders

### Order Flow

1. **Customer Places Order**: System captures order time
2. **Check Closing Time**: Compare against settings
3. **Calculate Next Business Day**:
    - Skip holidays (Type 1)
    - Skip Sundays
    - Include weekdays
    - Include working Saturdays (Type 2)
4. **Assign Delivery Date**: Order gets the calculated date
5. **Display to Customer**: Shows expected delivery date

### Example Scenario

**Setting**: Closing time is 3:00 PM (15:00)

**Scenario 1: Order at 2:00 PM on Wednesday**

-   Order placed: Wednesday 2:00 PM
-   Before closing time
-   Next business day: Thursday (if no holidays)
-   Delivery scheduled: Thursday

**Scenario 2: Order at 4:00 PM on Friday**

-   Order placed: Friday 4:00 PM
-   After closing time → Start from Saturday
-   Saturday is weekend → Skip to Monday
-   Monday is holiday → Skip to Tuesday
-   Delivery scheduled: Tuesday

**Scenario 3: Order on Friday with Working Saturday**

-   Order placed: Friday 2:00 PM
-   Next day: Saturday
-   Saturday marked as "Working Saturday" (Type 2) in holidays
-   Delivery scheduled: Saturday ✓

## Best Practices

### 1. Plan Ahead

-   Add holidays at the beginning of each year
-   Review and update working Saturdays monthly
-   Use CSV import for bulk additions

### 2. Coordinate with Delivery Team

-   Confirm which Saturdays will have delivery service
-   Add those as Type 2 (Sábado) holidays
-   Remove working Saturdays when service isn't available

### 3. Regular Review

-   Use "🔍 Debug Datos" to review holiday statistics
-   Check for any missing or duplicate holidays
-   Export data for backup

### 4. Test Delivery Calculations

-   After adding holidays, test order placement
-   Verify delivery dates are calculated correctly
-   Check that holiday dates are properly skipped

## Troubleshooting

### Orders Scheduled on Holidays

**Problem**: Orders being scheduled for delivery on a holiday
**Solution**:

1. Verify the holiday exists in the database
2. Check the `type_id` is set to 1 (Festivo)
3. Ensure the date format is correct (YYYY-MM-DD)

### Saturday Deliveries Not Working

**Problem**: Saturdays should have deliveries but are being skipped
**Solution**:

1. Add that specific Saturday as Type 2 (Sábado)
2. Verify the date is actually a Saturday
3. Check that it's not also marked as Type 1 (Holiday)

### Past Holidays Not Showing

**Problem**: Can't see older holidays
**Solution**:

-   Ensure "Mostrar festivos pasados" checkbox is checked (should be default)
-   If unchecked, check it and the form will submit automatically

## API/Integration

### Checking if a Date is a Business Day

```php
use App\Repositories\OrderRepository;

$date = Carbon::parse('2026-01-01');
$isBusinessDay = OrderRepository::isBussinessDay($date);
// Returns: false (New Year's Day)
```

### Getting Next Business Day

```php
use App\Repositories\OrderRepository;

$nextDelivery = OrderRepository::getBusinessDay(0); // 0 days ahead
// Returns: Next available business day as Y-m-d string
```

## Related Files

-   **Model**: `app/Models/Holiday.php`
-   **Controller**: `app/Http/Controllers/Admin/HolidayController.php`
-   **Repository**: `app/Repositories/OrderRepository.php`
-   **Migration**: `database/migrations/2023_05_14_033245_create_holidays_table.php`
-   **Views**: `resources/views/holidays/`

## Future Enhancements

Potential improvements:

1. **Auto-Import**: Automatically import national holidays from a calendar API
2. **Recurring Holidays**: Set up rules for holidays that repeat annually
3. **Zone-Specific Holidays**: Different holidays for different delivery zones
4. **Notification System**: Alert admins when holiday list is incomplete
5. **Calendar View**: Visual calendar interface for managing holidays

## Support

For questions or issues with the holidays system:

1. Check the Debug view for data verification
2. Review order delivery date calculations
3. Verify holidays are properly configured
4. Contact system administrator if issues persist
