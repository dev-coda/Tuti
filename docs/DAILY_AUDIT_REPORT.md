# Daily Audit Report

## Overview
The Daily Audit Report provides a comprehensive analysis of orders created on a specific date (defaults to yesterday), highlighting potential issues and special conditions like package quantities, bonifications, and suspicious pricing.

## Purpose
This report helps identify:
- Orders with products that have package quantities
- Orders containing bonification products
- Orders with suspicious pricing (products priced below $500)
- Overall order statistics for the day

## Usage

### Generating the Report

#### For Yesterday's Orders (Default)
```bash
php artisan orders:daily-audit
```

#### For a Specific Date
```bash
php artisan orders:daily-audit 2026-01-21
```

### Command Options
- `date` (optional): Date to audit in Y-m-d format (e.g., 2026-01-21)
  - If not provided, defaults to yesterday

### Output
The command will:
1. Generate an Excel file with the audit data
2. Save it to `storage/app/reports/orders_audit_YYYYMMDD_timestamp.xlsx`
3. Display a summary table showing:
   - Total orders
   - Orders with package quantities
   - Orders with bonifications
   - Orders with suspicious pricing

## Report Columns

| Column | Description |
|--------|-------------|
| ID Pedido | Order ID |
| Fecha Creación | Order creation date and time |
| Cliente | Customer name |
| Email Cliente | Customer email |
| Estado | Order status (Pendiente, Procesado, etc.) |
| Total | Order total amount |
| Descuento | Discount applied |
| Cant. Productos | Number of products in the order |
| Tiene Package Quantity | "SÍ" if any product has package_quantity > 0 |
| Tiene Bonificación | "SÍ" if any product is marked as bonification |
| Precio Sospechoso (<$500) | "SÍ" if any product is priced below $500 |
| Productos Sospechosos | Details of products with suspicious pricing |
| Vendedor | Seller assigned to the order |
| Zona | Zone assigned to the order |
| Ruta | Route assigned to the order |

## Red Flags to Watch For

### Suspicious Pricing (< $500)
Products priced below $500 may indicate:
- Data entry errors
- Pricing configuration issues
- Potential fraud
- Missing decimal points

**Action Required**: Review the "Productos Sospechosos" column for details.

### Package Quantities
Orders with package quantities should be verified to ensure:
- Correct inventory deductions
- Proper pricing calculations
- Accurate stock levels

### Bonifications
Orders with bonifications should be checked for:
- Correct application of promotional rules
- Proper tracking of promotional products
- Inventory impact

## Example Output

```
Generating daily audit report for: 2026-01-21

✓ Report generated successfully!

File path: /path/to/storage/app/reports/orders_audit_20260121_1234567890.xlsx

+----------------------------------+-------+------------+
| Metric                           | Count | Percentage |
+----------------------------------+-------+------------+
| Total Orders                     | 150   | 100%       |
| With Package Quantity            | 45    | 30.0%      |
| With Bonification                | 23    | 15.3%      |
| With Suspicious Pricing (<$500)  | 8     | 5.3%       |
+----------------------------------+-------+------------+
```

## Automation

### Daily Scheduled Report
To run this report automatically every day, add to your crontab or scheduler:

In `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Run daily audit report at 1 AM for yesterday's orders
    $schedule->command('orders:daily-audit')
             ->dailyAt('01:00')
             ->appendOutputTo(storage_path('logs/daily-audit.log'));
}
```

## Troubleshooting

### No Orders Found
If you see "No orders found for this date", verify:
- The date format is correct (Y-m-d)
- Orders exist for that date in the database
- You're checking the correct environment (production vs development)

### Report Generation Failed
Check the Laravel logs at `storage/logs/laravel.log` for detailed error information.

### Memory Issues
For dates with very large numbers of orders, the chunking (100 orders at a time) should prevent memory issues. If problems persist, you can modify the chunk size in `OrdersDailyAuditExport.php`.

## Integration with Reports System

This export can also be integrated into the existing Reports UI by:
1. Adding a new report type to the Reports model
2. Creating a controller action to trigger the export
3. Adding a UI button in the admin panel

## Technical Details

### Files Created
- `app/Exports/OrdersDailyAuditExport.php` - Export class
- `app/Console/Commands/GenerateDailyAuditReport.php` - Command class
- `docs/DAILY_AUDIT_REPORT.md` - This documentation

### Dependencies
- Laravel Excel (Maatwebsite)
- Order model with products relationship
- OrderProduct model with package_quantity, is_bonification, and price fields

### Performance
- Uses chunked reading (100 records at a time) to minimize memory usage
- Eager loads relationships to avoid N+1 queries
- Suitable for production use with large datasets
